<?php

// If a static landing page exists, serve it for root requests so the API
// can have a human-friendly homepage at '/'. This makes accessing
// http://localhost:8000 show public/index.html instead of routing to the API.
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
$reqPath = parse_url($reqUri, PHP_URL_PATH);
if ($reqPath === '/' || $reqPath === '/index.php') {
    $indexHtml = __DIR__ . '/index.html';
    if (is_file($indexHtml)) {
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents($indexHtml);
        exit;
    }
}

// Load Composer autoloader so vendor packages (e.g. mongodb/mongodb) are available
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/Bootstrap.php';

use BiwengerProManagerAPI\Bootstrap;
use BiwengerProManagerAPI\Response;

try {
    $bootstrap = new Bootstrap();
    $app = $bootstrap->getApp();
} catch (\Throwable $t) {
    // Any initialization error (DB driver missing, class not found, etc.) should return JSON
    Response::error($t->getMessage(), 500);
    exit;
}

// Simple routing: method + path
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove script name prefix if present (for built-in server)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/');
if ($base && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}

// Normalize
$uri = '/' . trim($uri, '/');

// Routes - versioned: /api/v0 for free/basic, /api/v1 for premium

// Helper to match either v0 or v1 prefix
function match_api($pattern, $uri)
{
    return preg_match('#^/api/(?:v0|v1)' . $pattern . '$#', $uri);
}

// Free/basic endpoints (v0)
try {
    if ($method === 'GET' && match_api('/leagues/?', $uri)) {
        $controller = $app->getController('LeagueController');
        $controller->index();
        exit;
    }

if ($method === 'GET' && preg_match('#^/api/(?:v0|v1)/leagues/(\d+)/?$#', $uri, $m)) {
    $controller = $app->getController('LeagueController');
    $controller->show((int)$m[1]);
    exit;
}

if ($method === 'GET' && match_api('/players/?', $uri)) {
    $controller = $app->getController('PlayerController');
    $controller->index();
    exit;
}

if ($method === 'GET' && preg_match('#^/api/(?:v0|v1)/players/(\d+)/?$#', $uri, $m)) {
    $controller = $app->getController('PlayerController');
    $controller->show((int)$m[1]);
    exit;
}

// Auth (free)
if ($method === 'POST' && match_api('/auth/login/?', $uri)) {
    $controller = $app->getController('AuthController');
    $controller->login();
    exit;
}

if ($method === 'POST' && match_api('/auth/token/?', $uri)) {
    $controller = $app->getController('AuthController');
    $controller->setToken();
    exit;
}

if ($method === 'GET' && match_api('/account/?', $uri)) {
    $controller = $app->getController('AuthController');
    $controller->account();
    exit;
}

if ($method === 'GET' && match_api('/rounds/?', $uri)) {
    $controller = $app->getController('RoundsController');
    $controller->index();
    exit;
}

if ($method === 'GET' && match_api('/rounds/results/?', $uri)) {
    $controller = $app->getController('RoundsController');
    $controller->results();
    exit;
}

// Users (free)
if ($method === 'GET' && match_api('/users/?', $uri)) {
    $controller = $app->getController('UsersController');
    $controller->index();
    exit;
}

if ($method === 'GET' && preg_match('#^/api/(?:v0|v1)/users/(\d+)/?$#', $uri, $m)) {
    $controller = $app->getController('UsersController');
    $controller->show((int)$m[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/api/(?:v0|v1)/users/(\d+)/players/?$#', $uri, $m)) {
    $controller = $app->getController('UsersController');
    $controller->players((int)$m[1]);
    exit;
}

if ($method === 'POST' && match_api('/users/sync/?', $uri)) {
    $controller = $app->getController('UsersController');
    $controller->sync();
    exit;
}

// Premium endpoints (v1): transfers & clauses
if ($method === 'POST' && preg_match('#^/api/v1/transfers/?$#', $uri)) {
    $controller = $app->getController('TransfersController');
    $controller->transfer();
    exit;
}

if ($method === 'POST' && preg_match('#^/api/v1/clauses/?$#', $uri)) {
    $controller = $app->getController('TransfersController');
    $controller->clause();
    exit;
}

// Premium endpoints: league settings (get/update)
if ($method === 'GET' && preg_match('#^/api/v1/leagues/(\d+)/settings/?$#', $uri, $m)) {
    $controller = $app->getController('LeagueController');
    if (method_exists($controller, 'getSettings')) $controller->getSettings((int)$m[1]);
    exit;
}

if (($method === 'POST' || $method === 'PUT') && preg_match('#^/api/v1/leagues/(\d+)/settings/?$#', $uri, $m)) {
    $controller = $app->getController('LeagueController');
    if (method_exists($controller, 'updateSettings')) $controller->updateSettings((int)$m[1]);
    exit;
}

    // Default 404
    Response::json(['error' => 'Not Found'], 404);
} catch (\Throwable $t) {
    // Map MongoDB connection timeouts to 503 Service Unavailable for clarity
    $status = 500;
    if (class_exists('MongoDB\\Driver\\Exception\\ConnectionTimeoutException') && is_a($t, 'MongoDB\\Driver\\Exception\\ConnectionTimeoutException')) {
        $status = 503;
    }
    Response::error($t->getMessage(), $status);
}
