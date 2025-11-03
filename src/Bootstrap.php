<?php
namespace BiwengerProManagerAPI;

use BiwengerProManagerAPI\Controllers\LeagueController;
use BiwengerProManagerAPI\Controllers\PlayerController;
use BiwengerProManagerAPI\Services\BiwengerClient;

class Bootstrap
{
    private $services = [];

    public function __construct()
    {
        $this->registerAutoloader();
        $this->registerServices();
    }

    private function registerAutoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = __NAMESPACE__ . '\\';
            // Map only our namespace or absolute
            $base_dir = __DIR__ . '/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // Try PSR-4 generic: replace namespace root with src/
                $relative = str_replace('\\', '/', $class);
                $file = __DIR__ . '/' . $relative . '.php';
                if (file_exists($file)) require $file;
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) require $file;
        });
    }

    private function registerServices()
    {
        $this->services['biwenger.client'] = function () {
            // BiwengerClient will use session-based token and headers established
            // after a successful login (AuthController) — no environment variables required.
            return new Services\BiwengerClient();
        };

        // Controller factories
        $this->services['LeagueController'] = function () {
            $client = $this->get('biwenger.client');
            $leaguesRepository = $this->get('LeaguesRepository');
            $settingsRepository = $this->get('SettingsRepository');
            $settingsService = $this->get('LeagueSettingsService');
            $ctrl = new Controllers\LeagueController(new Services\LeagueService($client, $leaguesRepository, $settingsRepository));
            // inject settings service if available for direct access
            if ($settingsService && method_exists($ctrl, 'setSettingsService')) $ctrl->setSettingsService($settingsService);
            return $ctrl;
        };

        $this->services['PlayerController'] = function () {
            // Reuse PlayerService from container
            return new Controllers\PlayerController($this->get('PlayerService'));
        };

        $this->services['AuthController'] = function () {
            $client = $this->get('biwenger.client');
            $accountService = $this->get('AccountService');
            return new Controllers\AuthController($client, $accountService);
        };

        $this->services['PlayerService'] = function () {
            $client = $this->get('biwenger.client');
            $leaguesRepository = $this->get('LeaguesRepository');
            return new Services\PlayerService($client, $leaguesRepository);
        };

        $this->services['RoundsService'] = function () {
            $client = $this->get('biwenger.client');
            return new Services\RoundsService($client);
        };

        $this->services['UsersService'] = function () {
            $client = $this->get('biwenger.client');
            $playerSvc = $this->get('PlayerService');
            $accountSvc = $this->get('AccountService');
            $leaguesRepo = $this->get('LeaguesRepository');
            $usersRepo = $this->get('UsersRepository');
            $clausRepo = $this->get('ClausulazosRepository');
            return new Services\UsersService($client, $playerSvc, $accountSvc, $leaguesRepo, $usersRepo, $clausRepo);
        };

        $this->services['LeagueSettingsService'] = function () {
            $settingsRepo = $this->get('SettingsRepository');
            $client = $this->get('biwenger.client');
            return new Services\LeagueSettingsService($settingsRepo, $client);
        };

        $this->services['SettingsRepository'] = function () {
            // Project now uses MongoDB as the sole persistence backend.
            if (class_exists('\BiwengerProManagerAPI\Database\MongoConnection')) {
                return new \BiwengerProManagerAPI\Database\SettingsRepository();
            }
            throw new \RuntimeException('MongoDB persistence not available: ensure the MongoDB PHP extension and the MongoConnection class are present.');
        };

        $this->services['LeaguesRepository'] = function () {
            // Project now uses MongoDB as the sole persistence backend.
            if (class_exists('\BiwengerProManagerAPI\Database\MongoConnection')) {
                $settingsRepo = $this->get('SettingsRepository');
                return new \BiwengerProManagerAPI\Database\LeaguesRepository(null, $settingsRepo);
            }
            throw new \RuntimeException('MongoDB persistence not available: ensure the MongoDB PHP extension and the MongoConnection class are present.');
        };

        $this->services['ClausulazosRepository'] = function () {
            if (class_exists('\BiwengerProManagerAPI\Database\MongoConnection')) {
                return new \BiwengerProManagerAPI\Database\ClausulazosRepository();
            }
            throw new \RuntimeException('MongoDB persistence not available: ensure the MongoDB PHP extension and the MongoConnection class are present.');
        };

        $this->services['AccountsRepository'] = function () {
            if (class_exists('\BiwengerProManagerAPI\Database\MongoConnection')) {
                return new \BiwengerProManagerAPI\Database\AccountsRepository();
            }
            throw new \RuntimeException('MongoDB persistence not available: ensure the MongoDB PHP extension and the MongoConnection class are present.');
        };

        $this->services['UsersRepository'] = function () {
            if (class_exists('\BiwengerProManagerAPI\Database\MongoConnection')) {
                return new \BiwengerProManagerAPI\Database\UsersRepository();
            }
            throw new \RuntimeException('MongoDB persistence not available: ensure the MongoDB PHP extension and the MongoConnection class are present.');
        };

        $this->services['AccountService'] = function () {
            $repository = $this->get('AccountsRepository');
            return new Services\AccountService($repository);
        };

        $this->services['TransfersService'] = function () {
            $client = $this->get('biwenger.client');
            $clausRepo = $this->get('ClausulazosRepository');
            return new Services\TransfersService($client, $clausRepo);
        };

        $this->services['RoundsController'] = function () {
            return new Controllers\RoundsController($this->get('RoundsService'));
        };

        $this->services['UsersController'] = function () {
            return new Controllers\UsersController($this->get('UsersService'));
        };

        $this->services['TransfersController'] = function () {
            return new Controllers\TransfersController($this->get('TransfersService'), $this->get('LeagueSettingsService'));
        };
    }

    public function get($key)
    {
        if (!isset($this->services[$key])) return null;
        $svc = $this->services[$key];
        return $svc();
    }

    public function getController($name)
    {
        if (isset($this->services[$name])) return $this->get($name);
        return null;
    }

    // For compatibility with public/index.php expectation
    public function getApp()
    {
        return $this;
    }
}
