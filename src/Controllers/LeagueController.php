<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\LeagueService;
use BiwengerProManagerAPI\Utils\ApiAuth;
use BiwengerProManagerAPI\Utils\Logger;

class LeagueController
{
    private $leagueService;
    private $settingsService;

    public function __construct(LeagueService $leagueService)
    {
        $this->leagueService = $leagueService;
    }

    // Optional injection for settings (registered in Bootstrap)
    public function setSettingsService($svc)
    {
        $this->settingsService = $svc;
    }

    /**
     * GET /api/v0/leagues
     * API version: v0 (public) - no API_KEY required
     * Returns list of leagues.
     */
    public function index()
    {
        try {
            // Require an explicit Authorization Bearer token to list leagues.
            $token = ApiAuth::requireBearerToken();

            Logger::info('LeagueController: index called, token=' . ($token ? '***' : 'null'));
            $leagues = $this->leagueService->getAll($token);
            Logger::info('LeagueController: retrieved ' . count($leagues) . ' leagues');
            Response::json(['status' => 200, 'message' => 'Leagues retrieved', 'data' => $leagues], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('LeagueController: exception in index: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/leagues/{id}
     * API version: v0 (public) - no API_KEY required
     * Returns a single league by id.
     */
    public function show($id)
    {
        try {
            // Require Authorization Bearer token to fetch league details
            $token = ApiAuth::requireBearerToken();

            Logger::info('LeagueController: show called id=' . $id . ', token=' . ($token ? '***' : 'null'));

            // validate id
            if (!is_numeric($id) || (int)$id <= 0) { Response::error('invalid league id', 400); return; }
            $league = $this->leagueService->getById((int)$id, $token);
            if (!$league) {
                Logger::error('LeagueController: league not found id=' . $id);
                Response::error('League not found', 404);
                return;
            }
            Logger::info('LeagueController: returning league id=' . $id);
            Response::json(['status' => 200, 'message' => 'League retrieved', 'data' => $league], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('LeagueController: exception in show: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/leagues/{id}/settings
     * API version: v1 (premium) - requires API_KEY
     * Returns league settings for a given league id.
     */
    public function getSettings($id)
    {
        // Protected endpoint: require API key
        ApiAuth::requireApiKey();

        $token = ApiAuth::requireBearerToken();

        try {
            if (!$this->settingsService) {
                Logger::error('LeagueController: settings service not available for league=' . $id);
                Response::error('Settings service not available', 500);
                return;
            }
            
            Logger::info('LeagueController: getSettings called id=' . $id . ', token=' . ($token ? '***' : 'null'));
            $settings = $this->settingsService->getSettings($id, $token);
            Logger::info('LeagueController: settings retrieved for league=' . $id);
            Response::json(['status' => 200, 'message' => 'Settings retrieved', 'data' => $settings], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('LeagueController: exception in getSettings: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST/PUT /api/v1/leagues/{id}/settings
     * API version: v1 (premium) - requires API_KEY
     * Updates settings for a given league id. Payload must include allowed settings.
     */
    public function updateSettings($id, string $rawInput = null)
    {
        // Protected endpoint: require API key
        ApiAuth::requireApiKey();

        $token = ApiAuth::requireBearerToken();

        try {
            if (!$this->settingsService) {
                Logger::error('LeagueController: updateSettings called but settings service not available for league=' . $id);
                Response::error('Settings service not available', 500);
                return;
            }
            $body = json_decode($rawInput ?? file_get_contents('php://input'), true);
            if (!is_array($body)) {
                Response::error('invalid payload', 400);
                return;
            }
            // Validate allowed fields and their types
            $allowed = ['clauses_value', 'times_can_clause', 'max_times_claused', 'num_rounds_to_unlock', 'num_days_before_round', 'max_players_same_team'];
            $sanitized = [];
            foreach ($body as $k => $v) {
                if (!in_array($k, $allowed, true)) continue; // ignore unrecognized keys
                switch ($k) {
                    case 'clauses_value':
                        if (!is_numeric($v) || (int)$v < 0) { Response::error('clauses_value must be non-negative integer', 400); return; }
                        $sanitized[$k] = (int)$v; break;
                    case 'times_can_clause':
                    case 'max_times_claused':
                    case 'num_rounds_to_unlock':
                    case 'num_days_before_round':
                    case 'max_players_same_team':
                        if (!is_numeric($v) || (int)$v < 0) { Response::error("{$k} must be non-negative integer", 400); return; }
                        $sanitized[$k] = (int)$v; break;
                }
            }

            if (empty($sanitized)) { Response::error('No valid settings provided', 400); return; }

            Logger::info('LeagueController: updateSettings for league=' . $id . ' payloadKeys=' . implode(',', array_keys($sanitized)));
            $ok = $this->settingsService->updateSettings($id, $sanitized);
            if ($ok) {
                Logger::info('LeagueController: settings updated for league=' . $id);
                Response::json(['status' => 200, 'message' => 'Settings updated', 'data' => null], 200);
            } else {
                Logger::error('LeagueController: failed to update settings for league=' . $id);
                Response::error('Failed to update settings', 500);
            }
        } catch (\Exception $e) {
            Logger::error('LeagueController: exception in updateSettings: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
