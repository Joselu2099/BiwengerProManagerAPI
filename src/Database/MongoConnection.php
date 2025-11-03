<?php
namespace BiwengerProManagerAPI\Database;

use MongoDB\Client;
use BiwengerProManagerAPI\Config\Config;

class MongoConnection
{
    private static $instance;
    private $client;
    private $db;

    private function __construct(string $uri = null, string $dbName = null)
    {
        // Prefer configuration file values, fallback to environment variables and defaults
        $cfgUri = Config::get('mongodb.uri');
        $cfgDb = Config::get('mongodb.db');

        $uri = $uri ?? $cfgUri ?? getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
        $dbName = $dbName ?? $cfgDb ?? getenv('MONGODB_DB') ?: 'biwenger';

        // Ensure the MongoDB client class is available and give a clear error if not.
        if (!class_exists('\\MongoDB\\Client')) {
            throw new \RuntimeException('MongoDB\\Client not found — please install the ext-mongodb PHP extension and the mongodb/mongodb composer package');
        }

        $this->client = new Client($uri);
        $this->db = $this->client->selectDatabase($dbName);
    }

    public static function getInstance(string $uri = null, string $dbName = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($uri, $dbName);
        }
        return self::$instance;
    }

    public function getDb()
    {
        return $this->db;
    }
}
