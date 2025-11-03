<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\RoundsService;
use BiwengerProManagerAPI\Utils\Logger;

class RoundsController
{
    private $service;

    public function __construct(RoundsService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v0/rounds
     * API version: v0 (public) - no API_KEY required
     * Returns list of rounds.
     */
    public function index()
    {
        try {
            Logger::info('RoundsController: index called');
            $rounds = $this->service->getAll();
            Logger::info('RoundsController: returning ' . count($rounds) . ' rounds');
            Response::json(['status' => 200, 'message' => 'Rounds retrieved', 'data' => $rounds], 200);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('RoundsController: exception in index: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/rounds/results
     * API version: v0 (public) - no API_KEY required
     * Returns rounds results.
     */
    public function results()
    {
        try {
            $token = null;
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['HTTP_AUTHORIZATION'];
                if (stripos($auth, 'bearer ') === 0) $token = trim(substr($auth, 7));
            }
            $league = $_GET['league'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);
            if ($league !== null) {
                $league = trim((string)$league);
                if ($league === '' || strlen($league) > 64) { Response::error('invalid league', 400); return; }
            }
            Logger::info('RoundsController: results called league=' . ($league ?? 'null'));
            $res = $this->service->getResults($token, $league);
            Logger::info('RoundsController: results count=' . (is_array($res) ? count($res) : 0));
            Response::json(['status' => 200, 'message' => 'Rounds results retrieved', 'data' => $res], 200);
        } catch (\Exception $e) {
            Logger::error('RoundsController: exception in results: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
