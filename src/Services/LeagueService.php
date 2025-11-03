<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Models\League;
use BiwengerProManagerAPI\Models\Setting;
use BiwengerProManagerAPI\Database\LeaguesRepository;
use BiwengerProManagerAPI\Database\SettingsRepository;
use BiwengerProManagerAPI\Utils\Logger;

class LeagueService
{
    private $client;
    private $leaguesRepository;
    private $settingsRepository;

    public function __construct(BiwengerClient $client, LeaguesRepository $leaguesRepository = null, SettingsRepository $settingsRepository = null)
    {
        $this->client = $client;
        $this->leaguesRepository = $leaguesRepository;
        $this->settingsRepository = $settingsRepository;
    }

    public function getAll($token = null): array
    {
        Logger::info('LeagueService: getAll called, token=' . ($token ? '***' : 'null'));
        // Allow optional token passed from controller
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        
        // Get leagues from Biwenger API
        $raw = $this->client->getLeagues($token);
        $result = [];
        
        foreach ($raw as $leagueData) {
            // Ensure league exists in our database and get complete settings
            $settings = $this->ensureLeagueInDatabaseAndGetSettings($leagueData, $token);
            
            // Create League model with all Biwenger data + complete Settings object
            $league = new League($leagueData, $settings);
            $result[] = $league;
        }
        
        Logger::info('LeagueService: returning ' . count($result) . ' leagues');
        return $result;
    }

    public function getById(int $id, $token = null)
    {
        Logger::info('LeagueService: getById called id=' . $id . ', token=' . ($token ? '***' : 'null'));
        if ($id <= 0) return null;
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        
        // Get league from Biwenger API
        $leagueData = $this->client->getLeagueById($id, $token);
        if (!$leagueData) {
            Logger::error('LeagueService: league not found id=' . $id);
            return null;
        }
        
        // Ensure league exists in our database and get complete settings
        $settings = $this->ensureLeagueInDatabaseAndGetSettings($leagueData, $token);
        
        Logger::info('LeagueService: getById returning league id=' . $id);
        return new League($leagueData, $settings);
    }

    /**
     * Ensure a league exists in our database and return its complete Settings object.
     * If it doesn't exist, create it with default values.
     */
    private function ensureLeagueInDatabaseAndGetSettings(array $leagueData, $token = null): Setting
    {
        $id = $leagueData['id'];
        $name = $leagueData['name'] ?? 'Unknown League';
        $competition = $leagueData['competition'] ?? '';
        $biwengerSettings = $leagueData['settings'] ?? [];

    if ($this->leaguesRepository) {
            // Check if league exists in our database
            if (!$this->leaguesRepository->existLeague($id)) {
                // Create new league with basic data
                $this->leaguesRepository->insertLeague($id, $name, $competition, $leagueData);
        Logger::info('LeagueService: inserted new league id=' . $id . ' name=' . $name);
                
                // Create default settings if repository is available
                if ($this->settingsRepository) {
                    $this->settingsRepository->insertSettings($id, $biwengerSettings, []);
                    Logger::info('LeagueService: inserted default settings for league id=' . $id);
                }
            } else {
                // Update league basic data in case it changed in Biwenger
                $this->leaguesRepository->updateLeague($id, $name, $competition, $leagueData);
                Logger::info('LeagueService: updated league id=' . $id);
                
                // Update Biwenger settings if repository is available
                if ($this->settingsRepository) {
                    if ($this->settingsRepository->existSettings($id)) {
                        $this->settingsRepository->updateBiwengerSettings($id, $biwengerSettings);
                        Logger::info('LeagueService: updated Biwenger settings for league id=' . $id);
                    } else {
                        $this->settingsRepository->insertSettings($id, $biwengerSettings, []);
                        Logger::info('LeagueService: inserted Biwenger settings for league id=' . $id);
                    }
                }
            }
        }

        // Get complete settings using the settings repository
        if ($this->settingsRepository) {
            $settings = $this->settingsRepository->getSettings($id);
            if ($settings) {
                return $settings;
            }
        }

        // Fallback: create basic Setting with Biwenger data only
        $defaultCustomSettings = [
            'clauses' => false,
            'clauses_value' => 200,
            'times_can_clause' => 1,
            'max_times_claused' => 1,
            'num_rounds_to_unlock' => 2,
            'num_days_before_round' => 2,
            'max_players_same_team' => 4
        ];

        return new Setting($biwengerSettings, $defaultCustomSettings);
    }
}
