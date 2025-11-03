<?php
// Quick test script to verify Logger writes to configured LOG_PATH
require_once __DIR__ . '/../src/Config/Config.php';
require_once __DIR__ . '/../src/Utils/Logger.php';

use BiwengerProManagerAPI\Config\Config;
use BiwengerProManagerAPI\Utils\Logger;

// Force load config
Config::load();
$path = Config::get('log.path');
echo "Configured log.path = " . var_export($path, true) . "\n";

Logger::info('TEST: logger info entry from test-logger.php');
Logger::error('TEST: logger error entry from test-logger.php');

echo "Wrote test log entries.\n";
