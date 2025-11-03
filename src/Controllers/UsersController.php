<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\UsersService;
use BiwengerProManagerAPI\Utils\ApiAuth;
use BiwengerProManagerAPI\Utils\Logger;

class UsersController
{
    private $service;

    public function __construct(UsersService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v0/users
     * API version: v0 (public) - no API_KEY required
     * Returns list of users from a league with their standings data.
     */
    public function index()
    {
        try {
            // Require Authorization Bearer token to fetch league details
            $token = ApiAuth::requireBearerToken();

            $league = $_GET['league'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);
            
            if (!$league) {
                Response::error('League parameter is required', 400);
                return;
            }

            Logger::info('UsersController: index called league=' . $league . ' token=' . ($token ? '***' : 'null'));
            $users = $this->service->getAll($token, $league);
            Logger::info('UsersController: getAll returned ' . count($users) . ' users for league=' . $league);
            Response::json(['status' => 200, 'message' => 'Users retrieved', 'data' => $users], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('UsersController: invalid argument in index: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('UsersController: exception in index: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/users/{id}
     * API version: v0 (public) - no API_KEY required
     * Returns a specific user by ID.
     */
    public function show($userId)
    {
        try {
            // Require Authorization Bearer token
            $token = ApiAuth::requireBearerToken();
            
            $league = $_GET['league'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);
            
            if (!$league) {
                Response::error('League parameter is required', 400);
                return;
            }

            // Validate userId
            if (!is_numeric($userId) || (int)$userId <= 0) {
                Response::error('Invalid user id', 400);
                return;
            }

            Logger::info('UsersController: show called userId=' . $userId . ' league=' . $league);
            $user = $this->service->getUser($userId, $league);
            
            if (!$user) {
                Logger::error('UsersController: user not found userId=' . $userId . ' league=' . $league);
                Response::error('User not found', 404);
                return;
            }

            Logger::info('UsersController: returning userId=' . $userId);
            Response::json(['status' => 200, 'message' => 'User retrieved', 'data' => $user], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('UsersController: invalid argument in show: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('UsersController: exception in show: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/users/{id}/players
     * API version: v0 (public) - no API_KEY required
     * Returns players for a given user id.
     */
    public function players($userId)
    {
        try {
            // Require Authorization Bearer token to fetch league details
            $token = ApiAuth::requireBearerToken();
            
            $league = $_GET['league'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);
            
            // Validate userId
            if (!is_numeric($userId) || (int)$userId <= 0) {
                Response::error('Invalid user id', 400);
                return;
            }

            Logger::info('UsersController: players called userId=' . $userId . ' league=' . ($league ?? 'null'));
            $players = $this->service->getPlayersOfUser((int)$userId, $token, (int)$league);
            Logger::info('UsersController: getPlayersOfUser returned ' . count($players) . ' players for userId=' . $userId);
            Response::json(['status' => 200, 'message' => 'User players retrieved', 'data' => $players], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('UsersController: invalid argument in players: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('UsersController: exception in players: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v0/users/sync
     * API version: v0 (public) - no API_KEY required
     * Sync users from Biwenger API to database
     */
    public function sync()
    {
        try {
            // Require Authorization Bearer token
            $token = ApiAuth::requireBearerToken();
            
            $league = $_GET['league'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? null);
            
            if (!$league) {
                Response::error('League parameter is required', 400);
                return;
            }

            Logger::info('UsersController: sync called league=' . $league . ' token=' . ($token ? '***' : 'null'));
            $result = $this->service->syncUsersFromApi($token, $league);
            Logger::info('UsersController: sync result=' . ($result ? 'success' : 'failure') . ' for league=' . $league);
            
            if ($result) {
                Response::json(['status' => 200, 'message' => 'Users synchronized successfully'], 200);
            } else {
                Response::error('Failed to synchronize users', 500);
            }
        } catch (\InvalidArgumentException $e) {
            Logger::error('UsersController: invalid argument in sync: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('UsersController: exception in sync: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
