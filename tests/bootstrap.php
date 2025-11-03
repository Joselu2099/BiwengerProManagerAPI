<?php
// Minimal bootstrap for tests
require_once __DIR__ . '/../src/Bootstrap.php';

// Ensure session available for classes that expect it
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Simple autoload via composer if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
