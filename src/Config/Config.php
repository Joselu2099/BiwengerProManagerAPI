<?php

namespace BiwengerProManagerAPI\Config;

/**
 * Lightweight configuration loader.
 * - Priority: config/{env}/.env -> config/{env}/config.conf -> config/{env}/config.php -> legacy config/{development|production}.php
 * - Environment variables override parsed values.
 * - Supports encrypted values stored as "ENC:<base64(iv+cipher)>"; use CONFIG_SECRET env var to (de)crypt.
 * - Convenience getters for bot credentials: getBotEmail(), getBotPassword().
 */
class Config
{
    private static bool $loaded = false;
    private static bool $loading = false;
    private static array $cfg = [];
    private static ?string $envDir = null;

    // Defaults
    private const DEFAULTS = [
        'db_driver' => 'mongodb',
    ];

    public static function load(): void
    {
        if (self::$loaded) return;
        if (self::$loading) return; // already in progress — avoid re-entrancy

        self::$loading = true;

        // Start with defaults
        self::$cfg = self::DEFAULTS;

        // Single-root .env configuration model
        // Read only the root config/.env file (project now uses a single env file)
        $rootDot = __DIR__ . '/../../config/.env';
        if (is_readable($rootDot)) {
            $lines = file($rootDot, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                // strip surrounding quotes if any
                if (preg_match('/^("|\')(.*)\\1$/', $v, $m) === 1) {
                    $v = $m[2];
                }
                // map known keys and expose APP_* vars
                switch (strtoupper($k)) {
                    case 'APP_ENV':
                        putenv("APP_ENV={$v}");
                        self::set('app.env', $v);
                        break;
                    case 'APP_PORT':
                        putenv("APP_PORT={$v}");
                        self::set('app.port', $v);
                        break;
                    default:
                        self::assignEnvKey($k, $v);
                        break;
                }
            }
        }

        // 5) environment variable overrides for known keys
        self::envOverride('DB_DRIVER', 'db_driver');
        self::envOverride('MONGODB_URI', 'mongodb.uri');
        self::envOverride('MONGODB_DB', 'mongodb.db');
        // SQLITE removed: environment-specific SQLite path handling is deprecated.
        self::envOverride('API_KEY', 'api.key');
        self::envOverride('CONFIG_SECRET', 'config.secret');
        self::envOverride('BOT_EMAIL', 'bot.email');
        self::envOverride('BOT_PASSWORD', 'bot.password');
        // Log path (directory or file). If unset, Logger will fallback to system temp dir.
        self::envOverride('LOG_PATH', 'log.path');

        self::$loaded = true;
        self::$loading = false;
    }

    private static function mapEnvName(string $raw): string
    {
        $r = strtolower($raw);
        if (in_array($r, ['dev', 'development'], true)) return 'dev';
        if (in_array($r, ['prod', 'production'], true)) return 'prod';
        if (strpos($r, 'local') !== false) return 'local';
        return $r;
    }

    private static function assignEnvKey(string $k, string $v): void
    {
        // strip quotes
        if (preg_match('/^("|\')(.*)\1$/', $v, $m)) $v = $m[2];
        // handle special keys mapping
        switch ($k) {
            case 'DB_DRIVER':
                self::set('db_driver', $v);
                break;
            case 'MONGODB_URI':
                self::set('mongodb.uri', $v);
                break;
            case 'MONGODB_DB':
                self::set('mongodb.db', $v);
                break;
            // SQLITE_PATH handling removed (using MongoDB by default)
            case 'API_KEY':
                self::set('api.key', $v);
                break;
            case 'BOT_EMAIL':
                self::set('bot.email', $v);
                break;
            case 'BOT_PASSWORD':
                self::set('bot.password', $v);
                break;
            case 'LOG_PATH':
                self::set('log.path', $v);
                break;
            default:
                // dotted keys allowed like mongodb.uri
                if (strpos($k, '.') !== false) self::set($k, $v);
                break;
        }
    }

    private static function envOverride(string $envName, string $keyPath): void
    {
        $v = getenv($envName);
        if ($v !== false && $v !== '') self::set($keyPath, $v);
    }

    /**
     * Get a config value using dot.path notation.
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded && !self::$loading) self::load();
        $parts = explode('.', $key);
        $cur = self::$cfg;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
            $cur = $cur[$p];
        }
        return $cur;
    }

    /**
     * Set a config value using dot.path notation.
     */
    public static function set(string $key, $value): void
    {
        if (!self::$loaded && !self::$loading) self::load();
        $parts = explode('.', $key);
        $ref = &self::$cfg;
        foreach ($parts as $p) {
            if (!isset($ref[$p]) || !is_array($ref[$p])) $ref[$p] = [];
            $ref = &$ref[$p];
        }
        $ref = $value;
    }

    /* ---------------- Bot helpers & encryption ---------------- */

    public static function getBotEmail(): ?string
    {
        $v = self::get('bot.email');
        if ($v === null) return null;
        return self::maybeDecrypt($v);
    }

    public static function getBotPassword(): ?string
    {
        $v = self::get('bot.password');
        if ($v === null) return null;
        return self::maybeDecrypt($v);
    }

    private static function maybeDecrypt($val)
    {
        if (!is_string($val)) return $val;
        $prefix = 'ENC:';
        if (strpos($val, $prefix) !== 0) return $val;
        $b64 = substr($val, strlen($prefix));
        return self::decryptValue($b64);
    }

    public static function encryptValue(string $plain): string
    {
        $secret = self::getSecret();
        if (empty($secret)) throw new \RuntimeException('CONFIG_SECRET is required to encrypt config values');
        $key = hash('sha256', $secret, true);
        // Use random_bytes when available for cryptographic randomness
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $ct = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        // Prefix with ENC: so stored values are clearly marked and maybeDecrypt can detect them
        return 'ENC:' . base64_encode($iv . $ct);
    }

    public static function decryptValue(string $b64): string
    {
        $secret = self::getSecret();
        if (empty($secret)) throw new \RuntimeException('CONFIG_SECRET is required to decrypt config values');
        $raw = base64_decode($b64);
        if ($raw === false || strlen($raw) < 17) throw new \RuntimeException('Invalid encrypted payload');
        $iv = substr($raw, 0, 16);
        $ct = substr($raw, 16);
        $key = hash('sha256', $secret, true);
        $plain = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) throw new \RuntimeException('Decryption failed');
        return $plain;
    }

    private static function getSecret(): ?string
    {
        $s = getenv('CONFIG_SECRET');
        if ($s !== false && $s !== '') return $s;
        // fallback to loaded config: support either config.secret or secret.key
        $cfgSecret = self::$cfg['config']['secret'] ?? null;
        if (empty($cfgSecret)) $cfgSecret = self::$cfg['secret']['key'] ?? null;
        return $cfgSecret;
    }

    /* ---------------- Utility ---------------- */
    public static function getEnvDir(): string
    {
        self::load();
        // With single-root .env model the env dir is the config folder
        return realpath(__DIR__ . '/../../config') ?: '';
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $out = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $part;
        }
        $res = implode('/', $out);
        return $res;
    }
}
