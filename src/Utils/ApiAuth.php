<?php
namespace BiwengerProManagerAPI\Utils;

use BiwengerProManagerAPI\Response;
use BiwengerProManagerAPI\Config\Config;
use BiwengerProManagerAPI\Utils\Logger;

class ApiAuth
{
    /**
     * Extract Bearer token from multiple possible sources for maximum compatibility.
     * Postman and some web servers don't always pass Authorization header consistently.
     */
    public static function extractBearerToken(): ?string
    {
        // Method 1: Standard Authorization header
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($auth, 'bearer ') === 0) {
                return trim(substr($auth, 7));
            }
        }

        // Method 2: Alternative Authorization header (some servers use this)
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (stripos($auth, 'bearer ') === 0) {
                return trim(substr($auth, 7));
            }
        }

        // Method 3: Custom header for compatibility (X-Authorization)
        if (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_X_AUTHORIZATION'];
            if (stripos($auth, 'bearer ') === 0) {
                return trim(substr($auth, 7));
            }
        }

        // Method 4: Query parameter fallback (useful for testing)
        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
        }

        return null;
    }

    /**
     * Require Bearer token for request, send 401 on failure and return the token.
     * If no token found, sends error response and exits.
     */
    public static function requireBearerToken(): string
    {
        $token = self::extractBearerToken();
        if (empty($token)) {
            Logger::error('ApiAuth: missing bearer token in request');
            Response::error('authorization token required', 401);
            exit;
        }
        return $token;
    }

    /**
     * Retrieve API key from headers or query string
     */
    public static function getApiKeyFromRequest(): ?string
    {
        // Common header names
        $headers = [];
        if (!empty($_SERVER['HTTP_X_API_KEY'])) $headers[] = $_SERVER['HTTP_X_API_KEY'];
        if (!empty($_SERVER['HTTP_API_KEY'])) $headers[] = $_SERVER['HTTP_API_KEY'];
        // Authorization: Bearer <key>
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($auth, 'bearer ') === 0) $headers[] = trim(substr($auth, 7));
        }
        // query param fallback
        if (!empty($_GET['api_key'])) $headers[] = $_GET['api_key'];

        foreach ($headers as $h) {
            if (is_string($h) && $h !== '') return $h;
        }
        return null;
    }

    /**
     * Check API key validity against config or environment
     */
    public static function verifyApiKey(?string $key): bool
    {
        $expected = Config::get('api.key') ?? getenv('API_KEY') ?: null;
        if (empty($expected)) {
            Logger::error('ApiAuth: API_KEY not configured in config or environment');
            return false;
        }
        return hash_equals((string)$expected, (string)($key ?? ''));
    }

    /**
     * Require API key for request, send 401 on failure and exit.
     */
    public static function requireApiKey(): void
    {
        $key = self::getApiKeyFromRequest();
        if (!$key || !self::verifyApiKey($key)) {
            Logger::error('ApiAuth: unauthorized access attempt via API key');
            Response::error('Unauthorized: invalid API key', 401);
            // Stop execution
            exit;
        }
    }
}
