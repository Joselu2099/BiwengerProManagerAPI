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

        ob_start(); $ctrl->index(); $out = ob_get_clean();
        $this->assertStringContainsString('P1', $out);

        ob_start(); $ctrl->show(10); $out2 = ob_get_clean();
        $this->assertStringContainsString('P1', $out2);

        ob_start(); $ctrl->show(999); $out3 = ob_get_clean();
        $this->assertStringContainsString('Player not found', $out3);
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
            public function getAll() { return [['id'=>2,'name'=>'U1']]; }
            public function getPlayersOfUser($id) { return $id==2 ? [['id'=>100]] : []; }
        };

        $ref = new \ReflectionClass(UsersController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('service');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $service);

        ob_start(); $ctrl->index(); $out = ob_get_clean();
        $this->assertStringContainsString('U1', $out);

        ob_start(); $ctrl->players(2); $out2 = ob_get_clean();
        $this->assertStringContainsString('100', $out2);
    }

    public function testAuthSetTokenLoginAndAccount()
    {
        $client = new class {
            public function setAuth($token) { /* store token in session handled by controller */ }
            public function getAccount() { return ['id'=>5,'name'=>'acct']; }
            public function login($email,$password) { if ($email==='a' && $password==='b') { $_SESSION['token']='tok'; return true; } return false; }
        };

        $ref = new \ReflectionClass(AuthController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $client);

        // set token
        ob_start(); $ctrl->setToken(json_encode(['token'=>'abc123'])); $out = ob_get_clean();
        $this->assertStringContainsString('token set', $out);

        // account
        ob_start(); $ctrl->account(); $out2 = ob_get_clean();
        $this->assertStringContainsString('acct', $out2);

        // login success
        ob_start(); $ctrl->login(json_encode(['email'=>'a','password'=>'b'])); $out3 = ob_get_clean();
        $this->assertStringContainsString('logged', $out3);

        // login fail
        ob_start(); $ctrl->login(json_encode(['email'=>'x','password'=>'y'])); $out4 = ob_get_clean();
        $this->assertStringContainsString('invalid credentials', $out4);
    }

}