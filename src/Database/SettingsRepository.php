<?php
namespace BiwengerProManagerAPI\Database;

use BiwengerProManagerAPI\Models\Setting;
use MongoDB\BSON\UTCDateTime;
use BiwengerProManagerAPI\Utils\Logger;

class SettingsRepository
{
    private $db;
    private $collectionName = 'settings';

    public function __construct($db = null)
    {
        // Accept a MongoDB\Database or create one via MongoConnection
        $this->db = $db ?? MongoConnection::getInstance()->getDb();
        // Create indexes for efficient queries
        $this->db->selectCollection($this->collectionName)->createIndex(['league_id' => 1], ['unique' => true]);
    }

    /**
     * Check if settings exist for a league
     */
    public function existSettings($leagueId): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne(['league_id' => (int)$leagueId]);
            return $doc !== null;
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::existSettings failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert new settings for a league
     */
    public function insertSettings($leagueId, array $biwengerSettings = [], array $customSettings = []): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);

            $defaultCustomSettings = [
                'clauses' => false,
                'clauses_value' => 200,
                'times_can_clause' => 1,
                'max_times_claused' => 1,
                'num_rounds_to_unlock' => 2,
                'num_days_before_round' => 2,
                'max_players_same_team' => 4
            ];

            $mergedCustomSettings = array_merge($defaultCustomSettings, $customSettings);

            $doc = [
                'league_id' => (int)$leagueId,
                // Biwenger settings
                'biwenger_settings' => $biwengerSettings,
                // Custom settings
                'clauses' => (bool)$mergedCustomSettings['clauses'],
                'clauses_value' => (int)$mergedCustomSettings['clauses_value'],
                'times_can_clause' => (int)$mergedCustomSettings['times_can_clause'],
                'max_times_claused' => (int)$mergedCustomSettings['max_times_claused'],
                'num_rounds_to_unlock' => (int)$mergedCustomSettings['num_rounds_to_unlock'],
                'num_days_before_round' => (int)$mergedCustomSettings['num_days_before_round'],
                'max_players_same_team' => (int)$mergedCustomSettings['max_players_same_team'],
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
            ];

            $res = $col->replaceOne(['league_id' => (int)$leagueId], $doc, ['upsert' => true]);
            Logger::info('SettingsRepository: insertSettings league_id=' . (int)$leagueId);
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::insertSettings failed: ' . $e->getMessage());
        }
    }

    /**
     * Get settings for a league and return a Setting object
     */
    public function getSettings($leagueId): ?Setting
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne(['league_id' => (int)$leagueId]);

            if (!$doc) {
                return null;
            }

            $arr = (array)$doc;

            // Extract Biwenger settings and ensure it's an array
            $biwengerSettings = $arr['biwenger_settings'] ?? [];
            if ($biwengerSettings instanceof \MongoDB\Model\BSONDocument) {
                $biwengerSettings = (array)$biwengerSettings;
            } elseif (is_object($biwengerSettings)) {
                $biwengerSettings = (array)$biwengerSettings;
            }
            
            // Extract custom settings
            $customSettings = [
                'clauses' => $arr['clauses'] ?? false,
                'clauses_value' => $arr['clauses_value'] ?? 0,
                'times_can_clause' => $arr['times_can_clause'] ?? 0,
                'max_times_claused' => $arr['max_times_claused'] ?? 0,
                'num_rounds_to_unlock' => $arr['num_rounds_to_unlock'] ?? 0,
                'num_days_before_round' => $arr['num_days_before_round'] ?? 0,
                'max_players_same_team' => $arr['max_players_same_team'] ?? 0
            ];

            return new Setting($biwengerSettings, $customSettings);
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::getSettings failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update settings for a league
     */
    public function updateSettings($leagueId, array $biwengerSettings = null, array $customSettings = null): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);

            $updateData = ['updated_at' => new \MongoDB\BSON\UTCDateTime()];

            // Update Biwenger settings if provided
            if ($biwengerSettings !== null) {
                $updateData['biwenger_settings'] = $biwengerSettings;
            }

            // Update custom settings if provided
            if ($customSettings !== null) {
                foreach ($customSettings as $key => $value) {
                    switch ($key) {
                        case 'clauses':
                            $updateData['clauses'] = (bool)$value;
                            break;
                        case 'clauses_value':
                        case 'times_can_clause':
                        case 'max_times_claused':
                        case 'num_rounds_to_unlock':
                        case 'num_days_before_round':
                        case 'max_players_same_team':
                            $updateData[$key] = (int)$value;
                            break;
                    }
                }
            }

            if (count($updateData) === 1) { // Only updated_at, no real changes
                return false;
            }

            $result = $col->updateOne(
                ['league_id' => (int)$leagueId],
                ['$set' => $updateData]
            );

            $ok = $result->getModifiedCount() > 0;
            if ($ok) Logger::info('SettingsRepository: updateSettings league_id=' . (int)$leagueId . ' modified=' . $result->getModifiedCount());
            return $ok;
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::updateSettings failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update only custom settings for a league
     */
    public function updateCustomSettings($leagueId, array $customSettings): bool
    {
        return $this->updateSettings($leagueId, null, $customSettings);
    }

    /**
     * Update only Biwenger settings for a league
     */
    public function updateBiwengerSettings($leagueId, array $biwengerSettings): bool
    {
        return $this->updateSettings($leagueId, $biwengerSettings, null);
    }

    /**
     * Delete settings for a league
     */
    public function deleteSettings($leagueId): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $result = $col->deleteOne(['league_id' => (int)$leagueId]);
            $deleted = $result->getDeletedCount();
            Logger::info('SettingsRepository: deleteSettings league_id=' . (int)$leagueId . ' deleted=' . $deleted);
            return $deleted > 0;
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::deleteSettings failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings
     */
    public function getAllSettings(): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find();
            $result = [];

            foreach ($cursor as $doc) {
                $arr = (array)$doc;
                $leagueId = $arr['league_id'];

                // Extract Biwenger settings and ensure it's an array
                $biwengerSettings = $arr['biwenger_settings'] ?? [];
                if ($biwengerSettings instanceof \MongoDB\Model\BSONDocument) {
                    $biwengerSettings = (array)$biwengerSettings;
                } elseif (is_object($biwengerSettings)) {
                    $biwengerSettings = (array)$biwengerSettings;
                }

                $customSettings = [
                    'clauses' => $arr['clauses'] ?? false,
                    'clauses_value' => $arr['clauses_value'] ?? 0,
                    'times_can_clause' => $arr['times_can_clause'] ?? 0,
                    'max_times_claused' => $arr['max_times_claused'] ?? 0,
                    'num_rounds_to_unlock' => $arr['num_rounds_to_unlock'] ?? 0,
                    'num_days_before_round' => $arr['num_days_before_round'] ?? 0,
                    'max_players_same_team' => $arr['max_players_same_team'] ?? 0
                ];

                $result[$leagueId] = new Setting($biwengerSettings, $customSettings);
            }

            Logger::info('SettingsRepository: getAllSettings count=' . count($result));
            return $result;
        } catch (\Throwable $e) {
            Logger::error('SettingsRepository::getAllSettings failed: ' . $e->getMessage());
            return [];
        }
    }
}