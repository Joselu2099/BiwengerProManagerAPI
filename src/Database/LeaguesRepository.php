<?php
namespace BiwengerProManagerAPI\Database;

use BiwengerProManagerAPI\Models\League;
use BiwengerProManagerAPI\Models\Setting;
use MongoDB\BSON\UTCDateTime;
use BiwengerProManagerAPI\Utils\Logger;

class LeaguesRepository
{
    private $db;
    private $collectionName = 'leagues';
    private $settingsRepository;

    public function __construct($db = null, SettingsRepository $settingsRepository = null)
    {
        // Accept a MongoDB\Database or create one via MongoConnection
        $this->db = $db ?? MongoConnection::getInstance()->getDb();
        $this->settingsRepository = $settingsRepository ?? new SettingsRepository($this->db);
        // Create indexes if necessary
        $this->db->selectCollection($this->collectionName)->createIndex(['_id' => 1]);
    }

    /**
     * Check if a league exists
     */
    public function existLeague($id): bool
    {
        $col = $this->db->selectCollection($this->collectionName);
        $doc = $col->findOne(['_id' => (int)$id]);
        return $doc !== null;
    }

    /**
     * Insert a new league with basic information only
     * Settings are handled separately by SettingsRepository
     */
    public function insertLeague($id, $name, $competition, array $leagueData = []): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            
            $doc = [
                '_id' => (int)$id,
                'name' => $name,
                'competition' => $competition,
                'scoreID' => $leagueData['scoreID'] ?? null,
                'type' => $leagueData['type'] ?? null,
                'mode' => $leagueData['mode'] ?? null,
                'marketMode' => $leagueData['marketMode'] ?? null,
                'created' => $leagueData['created'] ?? null,
                'icon' => $leagueData['icon'] ?? null,
                'cover' => $leagueData['cover'] ?? null,
                'upgrades' => $leagueData['upgrades'] ?? null,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
            ];

            $res = $col->replaceOne(['_id' => (int)$id], $doc, ['upsert' => true]);
            Logger::info('LeaguesRepository: insertLeague id=' . (int)$id);
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::insertLeague failed: ' . $e->getMessage());
        }
    }

    /**
     * Update league information (not settings)
     */
    public function updateLeague($id, $name = null, $competition = null, array $leagueData = []): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);

            $updateData = ['updated_at' => new \MongoDB\BSON\UTCDateTime()];

            if ($name !== null) $updateData['name'] = $name;
            if ($competition !== null) $updateData['competition'] = $competition;

            // Update other league fields if provided
            $allowedFields = ['scoreID', 'type', 'mode', 'marketMode', 'created', 'icon', 'cover', 'upgrades'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $leagueData)) {
                    $updateData[$field] = $leagueData[$field];
                }
            }

            if (count($updateData) === 1) { // Only updated_at, no real changes
                return false;
            }

            $result = $col->updateOne(
                ['_id' => (int)$id],
                ['$set' => $updateData]
            );

            $ok = $result->getModifiedCount() > 0;
            if ($ok) Logger::info('LeaguesRepository: updateLeague id=' . (int)$id . ' modified=' . $result->getModifiedCount());
            return $ok;
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::updateLeague failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a league and its associated settings
     */
    public function deleteLeague($id): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);

            // Delete associated settings first
            $this->settingsRepository->deleteSettings($id);

            // Delete the league
            $result = $col->deleteOne(['_id' => (int)$id]);
            $deleted = $result->getDeletedCount();
            Logger::info('LeaguesRepository: deleteLeague id=' . (int)$id . ' deleted=' . $deleted);
            return $deleted > 0;
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::deleteLeague failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all leagues (basic data only, without settings)
     */
    public function getAllLeagues(): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find();
            $out = [];

            foreach ($cursor as $doc) {
                $arr = (array)$doc;
                // Convert _id to id for compatibility
                if (isset($arr['_id'])) { 
                    $arr['id'] = $arr['_id']; 
                    unset($arr['_id']); 
                }
                // Remove MongoDB specific fields from output
                unset($arr['created_at'], $arr['updated_at']);
                $out[] = $arr;
            }

            Logger::info('LeaguesRepository: getAllLeagues count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::getAllLeagues failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get league basic data by ID (without settings)
     */
    public function getLeagueById($id): ?array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne(['_id' => (int)$id]);

            if (!$doc) return null;

            $arr = (array)$doc;
            if (isset($arr['_id'])) { 
                $arr['id'] = $arr['_id']; 
                unset($arr['_id']); 
            }
            // Remove MongoDB specific fields from output
            unset($arr['created_at'], $arr['updated_at']);

            return $arr;
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::getLeagueById failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get complete League object with Settings
     */
    public function getCompleteLeague($id): ?League
    {
        try {
            $leagueData = $this->getLeagueById($id);
            if (!$leagueData) return null;

            // Get settings for this league
            $settings = $this->settingsRepository->getSettings($id);

            return new League($leagueData, $settings);
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::getCompleteLeague failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all complete League objects with Settings
     */
    public function getAllCompleteLeagues(): array
    {
        try {
            $leagues = $this->getAllLeagues();
            $result = [];

            foreach ($leagues as $leagueData) {
                $id = $leagueData['id'];
                $settings = $this->settingsRepository->getSettings($id);
                $result[] = new League($leagueData, $settings);
            }

            Logger::info('LeaguesRepository: getAllCompleteLeagues count=' . count($result));
            return $result;
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::getAllCompleteLeagues failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create or update a complete league with settings
     */
    public function saveCompleteLeague($id, array $leagueData, array $biwengerSettings = [], array $customSettings = []): bool
    {
        try {
            $name = $leagueData['name'] ?? 'Unknown League';
            $competition = $leagueData['competition'] ?? '';

            // Insert/update league basic data
            if ($this->existLeague($id)) {
                $this->updateLeague($id, $name, $competition, $leagueData);
            } else {
                $this->insertLeague($id, $name, $competition, $leagueData);
            }

            // Insert/update settings
            if ($this->settingsRepository->existSettings($id)) {
                $ok = $this->settingsRepository->updateSettings($id, $biwengerSettings, $customSettings);
                Logger::info('LeaguesRepository: saveCompleteLeague id=' . (int)$id . ' updatedSettings=' . ($ok ? '1' : '0'));
                return $ok;
            } else {
                $this->settingsRepository->insertSettings($id, $biwengerSettings, $customSettings);
                Logger::info('LeaguesRepository: saveCompleteLeague id=' . (int)$id . ' insertedSettings=1');
                return true;
            }
        } catch (\Throwable $e) {
            Logger::error('LeaguesRepository::saveCompleteLeague failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @deprecated This method is deprecated. Use SettingsRepository directly for settings operations.
     */
    public function updateSettings($id, $settings): bool
    {
        // For backward compatibility, delegate to SettingsRepository
        return $this->settingsRepository->updateCustomSettings($id, $settings);
    }
}
