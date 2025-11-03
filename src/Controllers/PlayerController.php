<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\PlayerService;
use BiwengerProManagerAPI\Utils\Logger;

class PlayerController
{
    private $playerService;

    public function __construct(PlayerService $playerService)
    {
        $this->playerService = $playerService;
    }

    /**
     * GET /api/v0/players
     * API version: v0 (public) - no API_KEY required
     * Returns list of players.
     */
    public function index()
    {
        try {
            // Accept context via query params or headers: competition, scoreID or leagueId
            $competition = $_GET['competition'] ?? ($_SERVER['HTTP_X_COMPETITION'] ?? null);
            $scoreID = $_GET['scoreID'] ?? ($_SERVER['HTTP_X_SCOREID'] ?? ($_SERVER['HTTP_X_SCORE'] ?? null));
            $leagueId = $_GET['leagueId'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);

            // Require at least one context value so PlayerService can resolve where to fetch players from
            if ($competition === null && $scoreID === null && $leagueId === null) {
                Response::error('one of competition, scoreID or leagueId is required', 400);
                return;
            }

            Logger::info('PlayerController: index called context=' . json_encode(['competition' => $competition, 'scoreID' => $scoreID, 'leagueId' => $leagueId]));
            $players = $this->playerService->getAll($competition, $scoreID, $leagueId);
            Logger::info('PlayerController: getAll returned ' . count($players) . ' players');
            Response::json(['status' => 200, 'message' => 'Players retrieved', 'data' => $players], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('PlayerController: exception in index: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/players/{id}
     * API version: v0 (public) - no API_KEY required
     * Returns a single player by id.
     */
    public function show($id)
    {
        try {
            // Accept competition, scoreID or leagueId to resolve which competition to query
            $competition = $_GET['competition'] ?? ($_SERVER['HTTP_X_COMPETITION'] ?? null);
            $scoreID = $_GET['scoreID'] ?? ($_SERVER['HTTP_X_SCOREID'] ?? ($_SERVER['HTTP_X_SCORE'] ?? null));
            $leagueId = $_GET['leagueId'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);

            if ($competition === null && $scoreID === null && $leagueId === null) {
                Response::error('one of competition, scoreID or leagueId is required', 400);
                return;
            }
            Logger::info('PlayerController: show called id=' . $id . ' context=' . json_encode(['competition' => $competition, 'scoreID' => $scoreID, 'leagueId' => $leagueId]));
            if (!is_numeric($id) || (int)$id <= 0) { Response::error('invalid player id', 400); return; }
            // Delegate validation and resolution to the service which accepts any of the three
            $player = $this->playerService->getById((int)$id, $competition, $scoreID, $leagueId);
            if (!$player) {
                Logger::error('PlayerController: player not found id=' . $id);
                Response::error('Player not found', 404);
                return;
            }
            Logger::info('PlayerController: returning player id=' . $id);
            Response::json(['status' => 200, 'message' => 'Player retrieved', 'data' => $player], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('PlayerController: exception in show: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
