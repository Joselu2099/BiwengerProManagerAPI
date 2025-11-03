<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\TransfersService;
use BiwengerProManagerAPI\Services\LeagueSettingsService;
use BiwengerProManagerAPI\Utils\Logger;

class TransfersController
{
    private $service;
    private $settingsService;

    public function __construct(TransfersService $service, LeagueSettingsService $settingsService = null)
    {
        $this->service = $service;
        $this->settingsService = $settingsService;
    }

    /**
     * POST /api/v1/transfers
     * API version: v1 (premium) - requires API_KEY
     * Accept optional $rawInput for tests
     * Performs a player transfer. Payload must include playerId, fromUserId and toUserId.
     */
    public function transfer(string $rawInput = null)
    {
        // Protect with API key
        \BiwengerProManagerAPI\Utils\ApiAuth::requireApiKey();

        $body = json_decode($rawInput ?? file_get_contents('php://input'), true);
        if (!$body || !is_array($body)) {
            Response::error('invalid payload', 400);
            return;
        }
        // Basic payload validation
        $err = $this->validateTransferPayload($body);
        if ($err !== null) {
            Response::error($err, 400);
            return;
        }

        try {
            // Ensure league id present (in payload or headers)
            $leagueId = $body['leagueId'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? ($_GET['league'] ?? null));
            if (empty($leagueId)) { Response::error('leagueId is required', 400); return; }
            Logger::info('TransfersController: transfer called league=' . $leagueId . ' playerId=' . ($body['playerId'] ?? 'n/a') . ' from=' . ($body['fromUserId'] ?? 'n/a') . ' to=' . ($body['toUserId'] ?? 'n/a'));
            // Basic numeric validations
            if (!isset($body['playerId']) || !is_numeric($body['playerId']) || (int)$body['playerId'] <= 0) { Response::error('invalid playerId', 400); return; }
            if (!isset($body['fromUserId']) || !is_numeric($body['fromUserId']) || (int)$body['fromUserId'] <= 0) { Response::error('invalid fromUserId', 400); return; }
            if (!isset($body['toUserId']) || !is_numeric($body['toUserId']) || (int)$body['toUserId'] <= 0) { Response::error('invalid toUserId', 400); return; }

            $msg = $this->service->transferPlayer($body);
            Logger::info('TransfersController: transfer executed, msg=' . substr((string)$msg, 0, 200));
            Response::json(['status' => 200, 'message' => 'Transfer executed', 'data' => ['message' => $msg]], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('TransfersController: invalid transfer payload: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('TransfersController: exception in transfer: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/clauses
     * API version: v1 (premium) - requires API_KEY
     * Accept optional $rawInput for tests
     * Create/resolve a clause (clausula) for a player.
     */
    public function clause(string $rawInput = null)
    {
        // Protect with API key
        \BiwengerProManagerAPI\Utils\ApiAuth::requireApiKey();

        $body = json_decode($rawInput ?? file_get_contents('php://input'), true);
        if (!$body || !is_array($body)) {
            Response::error('invalid payload', 400);
            return;
        }
        $err = $this->validateClausePayload($body);
        if ($err !== null) {
            Response::error($err, 400);
            return;
        }

        try {
            // Determine token and x-headers for clause request
            $token = null;
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['HTTP_AUTHORIZATION'];
                if (stripos($auth, 'bearer ') === 0) $token = trim(substr($auth, 7));
            }
            $xLeague = $body['leagueId'] ?? ($_SERVER['HTTP_X_LEAGUE'] ?? ($_GET['league'] ?? null));
            $xUser = $body['xUser'] ?? ($_SERVER['HTTP_X_USER'] ?? ($body['fromUserId'] ?? null));

            Logger::info('TransfersController: clause called playerId=' . ($body['playerId'] ?? 'n/a') . ' amount=' . ($body['amount'] ?? 'n/a') . ' xLeague=' . ($xLeague ?? 'null'));
            $res = $this->service->clausePlayer($body, $token, $xLeague, $xUser);
            Logger::info('TransfersController: clause client response status=' . ($res['status'] ?? 'n/a'));
            $settings = null;
            if ($this->settingsService) {
                if ($xLeague) $settings = $this->settingsService->getSettings($xLeague);
            }
            Response::json(['status' => 200, 'message' => 'Clause processed', 'data' => ['result' => $res, 'settings' => $settings]], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('TransfersController: invalid clause payload: ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Logger::error('TransfersController: exception in clause: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    private function validateTransferPayload(array $body): ?string
    {
        // Basic required fields for a transfer
        $required = ['playerId', 'fromUserId', 'toUserId'];
        foreach ($required as $k) {
            if (!isset($body[$k])) return "missing field: $k";
        }
        // Normalize numeric values
        $playerId = (int)$body['playerId'];
        $fromUserId = (int)$body['fromUserId'];
        $toUserId = (int)$body['toUserId'];
        if ($playerId <= 0) return 'playerId must be positive integer';
        if ($fromUserId <= 0) return 'fromUserId must be positive integer';
        if ($toUserId <= 0) return 'toUserId must be positive integer';
        if ($fromUserId === $toUserId) return 'fromUserId and toUserId must be different';
        // price is optional but if present must be numeric and non-negative
        if (isset($body['price'])) {
            if (!is_numeric($body['price'])) return 'price must be numeric';
            if ((float)$body['price'] < 0) return 'price must be non-negative';
        }
        return null;
    }

    private function validateClausePayload(array $body): ?string
    {
        $required = ['playerId', 'clauseType', 'amount'];
        foreach ($required as $k) {
            if (!isset($body[$k])) return "missing field: $k";
        }
        $playerId = (int)$body['playerId'];
        if ($playerId <= 0) return 'playerId must be positive integer';
        $clauseType = trim((string)$body['clauseType']);
        if ($clauseType === '') return 'clauseType must be non-empty string';
        if (!is_numeric($body['amount'])) return 'amount must be numeric';
        if ((float)$body['amount'] < 0) return 'amount must be non-negative';
        return null;
    }
}
