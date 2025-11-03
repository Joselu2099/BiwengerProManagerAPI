<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Models\Setting;
use BiwengerProManagerAPI\Database\SettingsRepository;
use BiwengerProManagerAPI\Services\BiwengerClient;
use BiwengerProManagerAPI\Utils\Logger;

class LeagueSettingsService
{
    private $settingsRepository;
    private $client;

    public function __construct(SettingsRepository $settingsRepository = null, BiwengerClient $client = null)
    {
        $this->settingsRepository = $settingsRepository ?? new SettingsRepository();
        $this->client = $client;
    }

    /**
     * Get complete settings for a league, combining Biwenger settings with our custom settings
     */
    public function getSettings($leagueId, $token = null): Setting
    {
        Logger::info('LeagueSettingsService: getSettings called for league=' . $leagueId . ', token=' . ($token ? '***' : 'null'));
        // Get settings from repository first
        $existingSettings = $this->settingsRepository->getSettings($leagueId);
        
        // Get fresh Biwenger settings if client and token are available
        $biwengerSettings = [];
    if ($this->client && $token) {
            $leagueData = $this->client->getLeagueById($leagueId, $token);
            if ($leagueData && isset($leagueData['settings'])) {
                $biwengerSettings = $leagueData['settings'];
        Logger::info('LeagueSettingsService: got Biwenger settings from API for league=' . $leagueId);
                
                // Update Biwenger settings in repository if they exist
                if ($existingSettings) {
                    $this->settingsRepository->updateBiwengerSettings($leagueId, $biwengerSettings);
                    Logger::info('LeagueSettingsService: updated Biwenger settings in repository for league=' . $leagueId);
                }
            }
        }

        // If we have existing settings, return them (possibly with updated Biwenger data)
        if ($existingSettings) {
            // If we got fresh Biwenger settings, create a new Setting object with updated data
            if (!empty($biwengerSettings)) {
                $customSettings = [
                    'clauses' => $existingSettings->getClauses(),
                    'clauses_value' => $existingSettings->getClausesValue(),
                    'times_can_clause' => $existingSettings->getTimesCanClause(),
                    'max_times_claused' => $existingSettings->getMaxTimesClaused(),
                    'num_rounds_to_unlock' => $existingSettings->getNumRoundsToUnlock(),
                    'num_days_before_round' => $existingSettings->getNumDaysBeforeRound(),
                    'max_players_same_team' => $existingSettings->getMaxPlayersSameTeam()
                ];
                return new Setting($biwengerSettings, $customSettings);
            }
            return $existingSettings;
        }

        // No existing settings - create defaults
        $defaultCustomSettings = [
            'clauses' => false,
            'clauses_value' => 200,
            'times_can_clause' => 1,
            'max_times_claused' => 1,
            'num_rounds_to_unlock' => 2,
            'num_days_before_round' => 2,
            'max_players_same_team' => 4
        ];

        // Create and store new settings
    $this->settingsRepository->insertSettings($leagueId, $biwengerSettings, $defaultCustomSettings);
    Logger::info('LeagueSettingsService: inserted default settings for league=' . $leagueId);
        
        return new Setting($biwengerSettings, $defaultCustomSettings);
    }

    /**
     * Update custom settings for a league (only our custom settings, not Biwenger settings)
     */
    public function updateSettings($leagueId, array $settings): bool
    {
        // Validate that we only accept our custom settings
        $allowedCustomSettings = [
            'clauses', 'clauses_value', 'times_can_clause', 'max_times_claused',
            'num_rounds_to_unlock', 'num_days_before_round', 'max_players_same_team'
        ];

        $updateData = [];
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedCustomSettings)) {
                $updateData[$key] = $value;
            }
        }

        if (empty($updateData)) {
            return false;
        }

        // If settings don't exist, create them first
        if (!$this->settingsRepository->existSettings($leagueId)) {
            $this->settingsRepository->insertSettings($leagueId, [], $updateData);
            return true;
        }

        return $this->settingsRepository->updateCustomSettings($leagueId, $updateData);
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use getSettings() instead
     */
    public function getSettingsArray($leagueId): array
    {
        $setting = $this->getSettings($leagueId);
        return [
            'clausesValue' => $setting->getClausesValue(),
            'timesCanClause' => $setting->getTimesCanClause(),
            'maxTimesClaused' => $setting->getMaxTimesClaused(),
            'numRoundsToUnlock' => $setting->getNumRoundsToUnlock(),
            'numDaysBeforeRound' => $setting->getNumDaysBeforeRound(),
            'maxPlayersSameTeam' => $setting->getMaxPlayersSameTeam(),
        ];
    }
}
