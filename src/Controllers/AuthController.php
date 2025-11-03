<?php
namespace BiwengerProManagerAPI\Controllers;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Services\BiwengerClient;
use BiwengerProManagerAPI\Services\AccountService;
use BiwengerProManagerAPI\Utils\ApiAuth;
use BiwengerProManagerAPI\Utils\Logger;

class AuthController
{
    private $client;
    private $accountService;

    public function __construct(BiwengerClient $client, AccountService $accountService = null)
    {
        $this->client = $client;
        $this->accountService = $accountService;
    }

    /**
     * POST /api/v0/auth/login
     * API version: v0 (public) - no API_KEY required
     * Accept optional $rawInput for testing to avoid relying on php://input
     * Authenticate user using email/password. Returns token on success.
     */
    public function login(string $rawInput = null)
    {
        $body = json_decode($rawInput ?? file_get_contents('php://input'), true);
        if (!isset($body['email']) || !isset($body['password'])) {
            Response::error('email and password required', 400);
            return;
        }

        try {
            // Validate input
            $email = filter_var(trim((string)$body['email']), FILTER_VALIDATE_EMAIL);
            $password = isset($body['password']) ? trim((string)$body['password']) : '';
            if (!$email) { Response::error('invalid email format', 400); return; }
            if ($password === '' || strlen($password) < 4) { Response::error('password required (min length 4)', 400); return; }

            // Client returns token (or null) — do not rely on session storage
            Logger::info('AuthController: login attempt for ' . ($email ?? 'unknown'));
            $token = $this->client->getToken($email, $password);
            if ($token) {
                Logger::info('AuthController: login successful for ' . ($email ?? 'unknown'));
                // Get account data and store/update in database
                $accountData = $this->client->getAccount($token);
                if ($accountData && $this->accountService) {
                    $accountData['token'] = $token;
                    $account = $this->accountService->processAccountData($accountData);
                    Response::json([
                        'status' => 200, 
                        'message' => 'Logged in', 
                        'data' => $account
                    ], 200);
                } else {
                    Logger::info('AuthController: account data not available, returning token only');
                    // Fallback if account data retrieval fails
                    Response::json(['status' => 200, 'message' => 'Logged in', 'data' => ['token' => $token]], 200);
                }
            } else {
                Logger::error('AuthController: invalid credentials for ' . ($email ?? 'unknown'));
                Response::error('invalid credentials', 401);
            }
        } catch (\Exception $e) {
            Logger::error('AuthController: exception during login: ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v0/account
     * API version: v0 (public) - no API_KEY required
     * Returns the account info for the current token.
     */
    public function account()
    {
        try {
            $token = ApiAuth::extractBearerToken();
            if (empty($token)) {
                Logger::error('AuthController: account called without token');
                Response::error('authorization token required', 401);
                return;
            }

            Logger::info('AuthController: fetching account for token');
            $accountData = $this->client->getAccount($token);
            if (!$accountData) {
                Logger::error('AuthController: account not found or invalid token');
                Response::error('account not found or invalid token', 404);
                return;
            }

            // Ensure token is preserved when storing account locally
            if (is_array($accountData)) {
                $accountData['token'] = $token;
            }

            // Store/update account in database and return Account model
            if ($this->accountService) {
                $account = $this->accountService->processAccountData($accountData);
                Logger::info('AuthController: account data processed and persisted');
                Response::json(['status' => 200, 'message' => 'Account retrieved', 'data' => $account], 200);
            } else {
                // Fallback to raw account data if service not available
                Logger::info('AuthController: account service not available, returning raw account data');
                Response::json(['status' => 200, 'message' => 'Account retrieved', 'data' => $accountData], 200);
            }
        } catch (\Exception $e) {
            Logger::error('AuthController: exception in account(): ' . $e->getMessage());
            Response::error($e->getMessage(), 500);
        }
    }
}
