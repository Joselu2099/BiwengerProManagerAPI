<?php
use PHPUnit\Framework\TestCase;

use BiwengerProManagerAPI\Controllers\LeagueController;
use BiwengerProManagerAPI\Controllers\PlayerController;
use BiwengerProManagerAPI\Controllers\RoundsController;
use BiwengerProManagerAPI\Controllers\UsersController;
use BiwengerProManagerAPI\Controllers\AuthController;
use BiwengerProManagerAPI\Controllers\TransfersController;
use BiwengerProManagerAPI\Config\Config;

// Not API Key needed -> v0 are public endpoints
class V0EndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        Config::load();
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid_token';
    }

    public function testLeaguesIndexAndShow()
    {
        $service = new class {
            public function getAll() { return [['id'=>1,'name'=>'L1']]; }
            public function getById($id) { return $id==1 ? ['id'=>1,'name'=>'L1'] : null; }
        };

        $ref = new \ReflectionClass(LeagueController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('leagueService');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $service);

        ob_start();
        $ctrl->index();
        $out = ob_get_clean();
        $this->assertStringContainsString('L1', $out, 'League index should contain league name');

        ob_start();
        $ctrl->show(1);
        $out2 = ob_get_clean();
        $this->assertStringContainsString('L1', $out2, 'League show should return the league');

        ob_start();
        $ctrl->show(999);
        $out3 = ob_get_clean();
        $this->assertTrue(strpos($out3,'League not found')!==false || strpos($out3,'Not Found')!==false, 'Expected not found for missing league');
    }

    public function testPlayersIndexAndShow()
    {
        $service = new class {
            public function getAll() { return [['id'=>10,'name'=>'P1']]; }
            public function getById($id) { return $id==10 ? ['id'=>10,'name'=>'P1'] : null; }
        };

        $ref = new \ReflectionClass(PlayerController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('playerService');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $service);

        $_GET['competition'] = 'test_comp';

        ob_start(); $ctrl->index(); $out = ob_get_clean();
        $this->assertStringContainsString('P1', $out);

        ob_start(); $ctrl->show(10); $out2 = ob_get_clean();
        $this->assertStringContainsString('P1', $out2);

        ob_start(); $ctrl->show(999); $out3 = ob_get_clean();
        $this->assertStringContainsString('Player not found', $out3);

        ob_start(); $ctrl->show('invalid'); $out4 = ob_get_clean();
        $this->assertStringContainsString('invalid player id', $out4);

        unset($_GET['competition']);

        // Check required context
        ob_start(); $ctrl->index(); $outMissingContext = ob_get_clean();
        $this->assertStringContainsString('one of competition, scoreID or leagueId is required', $outMissingContext);
    }

    public function testRoundsIndexAndResults()
    {
        $service = new class {
            public function getAll() { return [['round'=>1]]; }
            public function getResults() { return [['result'=>true]]; }
        };

        $ref = new \ReflectionClass(RoundsController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('service');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $service);

        ob_start(); $ctrl->index(); $out = ob_get_clean();
        $this->assertStringContainsString('round', $out);

        ob_start(); $ctrl->results(); $out2 = ob_get_clean();
        $this->assertStringContainsString('result', $out2);
    }

    public function testUsersIndexAndPlayers()
    {
        $service = new class {
            public function getAll($token, $league) { return $league == 1 ? [['id'=>2,'name'=>'U1']] : []; }
            public function getUser($id, $league) { return ($id == 2 && $league == 1) ? ['id'=>2,'name'=>'U1'] : null; }
            public function getPlayersOfUser($id, $token, $league) { return ($id==2 && $league == 1) ? [['id'=>100]] : []; }
            public function syncUsersFromApi($token, $league) { return $league == 1; }
        };

        $ref = new \ReflectionClass(UsersController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('service');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $service);

        $_GET['league'] = '1';

        ob_start(); $ctrl->index(); $out = ob_get_clean();
        $this->assertStringContainsString('U1', $out);

        ob_start(); $ctrl->show(2); $outShow = ob_get_clean();
        $this->assertStringContainsString('U1', $outShow);

        ob_start(); $ctrl->show(999); $outShowNotFound = ob_get_clean();
        $this->assertStringContainsString('User not found', $outShowNotFound);

        ob_start(); $ctrl->players(2); $out2 = ob_get_clean();
        $this->assertStringContainsString('100', $out2);

        ob_start(); $ctrl->sync(); $outSync = ob_get_clean();
        $this->assertStringContainsString('Users synchronized successfully', $outSync);

        unset($_GET['league']);

        // Missing league param
        ob_start(); $ctrl->index(); $outMissingLeague = ob_get_clean();
        $this->assertStringContainsString('League parameter is required', $outMissingLeague);

        ob_start(); $ctrl->show(2); $outShowMissingLeague = ob_get_clean();
        $this->assertStringContainsString('League parameter is required', $outShowMissingLeague);

        ob_start(); $ctrl->sync(); $outSyncMissingLeague = ob_get_clean();
        $this->assertStringContainsString('League parameter is required', $outSyncMissingLeague);
    }

    public function testAuthSetTokenLoginAndAccount()
    {
        $client = new class {
            public function setAuth($token) { /* store token in session handled by controller */ }
            public function getAccount($token = null) { return ($token === 'valid_token' || empty($token)) ? ['id'=>5,'name'=>'acct'] : null; }
            public function getToken($email, $password) {
                if ($email === 'a@example.com' && $password === 'password') {
                    return 'new_token';
                }
                return null;
            }
            public function login($email,$password) { if ($email==='a@example.com' && $password==='password') { return true; } return false; }
        };

        $accountService = new class {
            public function processAccountData($data) { return $data; }
        };

        $ref = new \ReflectionClass(AuthController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();

        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $client);

        $propAccount = $ref->getProperty('accountService');
        $propAccount->setAccessible(true);
        $propAccount->setValue($ctrl, $accountService);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid_token';

        // set token
        ob_start(); $ctrl->setToken(json_encode(['token'=>'abc123'])); $out = ob_get_clean();
        $this->assertStringContainsString('token set', $out);

        // account
        ob_start(); $ctrl->account(); $out2 = ob_get_clean();
        $this->assertStringContainsString('acct', $out2);

        // login success
        ob_start(); $ctrl->login(json_encode(['email'=>'a@example.com','password'=>'password'])); $out3 = ob_get_clean();
        $this->assertStringContainsString('Logged in', $out3);

        // login fail
        ob_start(); $ctrl->login(json_encode(['email'=>'x@example.com','password'=>'wrong'])); $out4 = ob_get_clean();
        $this->assertStringContainsString('invalid credentials', $out4);
    }

}