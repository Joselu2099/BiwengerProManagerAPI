<?php
use PHPUnit\Framework\TestCase;

use BiwengerProManagerAPI\Controllers\LeagueController;
use BiwengerProManagerAPI\Controllers\PlayerController;
use BiwengerProManagerAPI\Controllers\RoundsController;
use BiwengerProManagerAPI\Controllers\UsersController;
use BiwengerProManagerAPI\Controllers\AuthController;
use BiwengerProManagerAPI\Controllers\TransfersController;
use BiwengerProManagerAPI\Config\Config;
use BiwengerProManagerAPI\Utils\ApiAuth;

// API Key needed -> v1 are premium endpoints
class V1EndpointsTest extends TestCase
{
    private $originalApiKey;
    private $originalHeaders;

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        Config::load();
        
        // Backup original values
        $this->originalApiKey = Config::get('api.key');
        $this->originalHeaders = $_SERVER;
        
        // Set test API key
        Config::set('api.key', 'test_api_key');
        $_SERVER['HTTP_X_API_KEY'] = 'test_api_key';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_123';
    }

    protected function tearDown(): void
    {
        // Restore original values
        if ($this->originalApiKey !== null) {
            Config::set('api.key', $this->originalApiKey);
        }
        $_SERVER = $this->originalHeaders;
    }

    public function testApiKeyIsRecognized()
    {
        // ApiAuth should recognize the key set in setUp()
        $this->assertTrue(ApiAuth::verifyApiKey('test_api_key'));
        $this->assertTrue(ApiAuth::verifyApiKey($_SERVER['HTTP_X_API_KEY'] ?? ''));
    }

    public function testApiKeyAuthenticationWithInvalidKey()
    {
        $this->assertFalse(ApiAuth::verifyApiKey('invalid_key'));
    }

    // === INTEGRATION TESTS WITH REAL CONTROLLERS ===

    public function testTransferRequiresApiKey()
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        
        // Create a minimal mock for TransfersService
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        ob_start();
        try {
            $controller->transfer('{"playerId": 123, "fromUserId": 456, "toUserId": 789}');
        } catch (\Exception $e) {}
        $out = ob_get_clean();
        
        $this->assertStringContainsString('invalid API key', $out);
    }

    public function testTransferValidatesPayload()
    {
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        // Test missing required field
        ob_start();
        $controller->transfer('{"fromUserId": 456, "toUserId": 789}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('missing field: playerId', $response['message']);
    }

    public function testTransferValidatesInvalidPlayerId()
    {
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        // Test invalid playerId
        ob_start();
        $controller->transfer('{"playerId": 0, "fromUserId": 456, "toUserId": 789}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('playerId must be positive integer', $response['message']);
    }

    public function testTransferValidatesSameUserIds()
    {
        $_SERVER['HTTP_X_LEAGUE'] = '1358641';
        
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        // Test same fromUserId and toUserId
        ob_start();
        $controller->transfer('{"playerId": 123, "fromUserId": 456, "toUserId": 456}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('fromUserId and toUserId must be different', $response['message']);
    }

    public function testClauseRequiresApiKey()
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        ob_start();
        try {
            $controller->clause('{"playerId": 123, "clauseType": "buy", "amount": 1000000}');
        } catch (\Exception $e) {}
        $out = ob_get_clean();
        
        $this->assertStringContainsString('invalid API key', $out);
    }

    public function testClauseValidatesRequiredFields()
    {
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        // Test missing clauseType
        ob_start();
        $controller->clause('{"playerId": 123, "amount": 1000000}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('missing field: clauseType', $response['message']);
    }

    public function testClauseValidatesNegativeAmount()
    {
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->createMock(\BiwengerProManagerAPI\Services\TransfersService::class);
        
        $controller = new TransfersController($mockTransfersService);
        
        // Test negative amount
        ob_start();
        $controller->clause('{"playerId": 123, "clauseType": "buy", "amount": -100}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('amount must be non-negative', $response['message']);
    }

    public function testInvalidJsonPayload()
    {
        /** @var \BiwengerProManagerAPI\Services\TransfersService&\PHPUnit\Framework\MockObject\MockObject $mockTransfersService */
        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new TransfersController($mockTransfersService);
        
        ob_start();
        $controller->transfer('invalid json');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertEquals('invalid payload', $response['message']);
    }

    public function testTransferSuccess()
    {
        $_SERVER['HTTP_X_LEAGUE'] = '123';

        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockTransfersService->expects($this->once())
            ->method('transferPlayer')
            ->willReturn('Transfer successful');

        $controller = new TransfersController($mockTransfersService);

        ob_start();
        $controller->transfer('{"playerId": 123, "fromUserId": 456, "toUserId": 789, "price": 100}');
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('Transfer executed', $response['message']);
        $this->assertEquals('Transfer successful', $response['data']['message']);
    }

    public function testClauseSuccess()
    {
        $_SERVER['HTTP_X_LEAGUE'] = '123';
        $_SERVER['HTTP_X_USER'] = '456';

        $mockTransfersService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\TransfersService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockTransfersService->expects($this->once())
            ->method('clausePlayer')
            ->willReturn(['status' => 'ok']);

        $mockSettingsService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueSettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $settingMock = new \BiwengerProManagerAPI\Models\Setting([], ['clauses_value' => 100]);
        $mockSettingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn($settingMock);

        $controller = new TransfersController($mockTransfersService, $mockSettingsService);

        ob_start();
        $controller->clause('{"playerId": 123, "clauseType": "buy", "amount": 1000000}');
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('Clause processed', $response['message']);
        $this->assertEquals('ok', $response['data']['result']['status']);
        $this->assertEquals(100, $response['data']['settings']['clauses_value']);
    }

    // === LEAGUE CONTROLLER TESTS ===

    public function testLeagueGetSettingsSuccess()
    {
        $mockLeagueService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockSettingsService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueSettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $settingMock = new \BiwengerProManagerAPI\Models\Setting([], ['clauses_value' => 100]);
        $mockSettingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn($settingMock);

        $controller = new LeagueController($mockLeagueService);
        $controller->setSettingsService($mockSettingsService);

        ob_start();
        $controller->getSettings(123);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('Settings retrieved', $response['message']);
        $this->assertEquals(100, $response['data']['clauses_value']);
    }

    public function testLeagueUpdateSettingsSuccess()
    {
        $mockLeagueService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockSettingsService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueSettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSettingsService->expects($this->once())
            ->method('updateSettings')
            ->willReturn(true);

        $controller = new LeagueController($mockLeagueService);
        $controller->setSettingsService($mockSettingsService);

        ob_start();
        $controller->updateSettings(123, '{"clauses_value": 100, "max_players_same_team": 3}');
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('Settings updated', $response['message']);
    }

    public function testLeagueUpdateSettingsValidatesNegativeValues()
    {
        /** @var \BiwengerProManagerAPI\Services\LeagueService&\PHPUnit\Framework\MockObject\MockObject $mockLeagueService */
        $mockLeagueService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        /** @var \BiwengerProManagerAPI\Services\LeagueSettingsService&\PHPUnit\Framework\MockObject\MockObject $mockSettingsService */
        $mockSettingsService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueSettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new LeagueController($mockLeagueService);
        $controller->setSettingsService($mockSettingsService);
        
        // Test negative clauses_value
        ob_start();
        $controller->updateSettings(123, '{"clauses_value": -100}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('non-negative integer', $response['message']);
    }

    public function testLeagueUpdateSettingsValidatesInvalidKeys()
    {
        /** @var \BiwengerProManagerAPI\Services\LeagueService&\PHPUnit\Framework\MockObject\MockObject $mockLeagueService */
        $mockLeagueService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        /** @var \BiwengerProManagerAPI\Services\LeagueSettingsService&\PHPUnit\Framework\MockObject\MockObject $mockSettingsService */
        $mockSettingsService = $this->getMockBuilder(\BiwengerProManagerAPI\Services\LeagueSettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $controller = new LeagueController($mockLeagueService);
        $controller->setSettingsService($mockSettingsService);
        
        // Test with no valid settings (only invalid keys)
        ob_start();
        $controller->updateSettings(123, '{"invalid_key": 123, "another_invalid": 456}');
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('No valid settings provided', $response['message']);
    }

    // === API KEY EXTRACTION TESTS ===

    public function testApiKeyExtractionFromHeader()
    {
        Config::set('api.key', 'header_api_key');
        $_SERVER['HTTP_X_API_KEY'] = 'header_api_key';
        unset($_GET['api_key']);
        
        $this->assertTrue(ApiAuth::verifyApiKey('header_api_key'));
    }

    public function testBearerTokenExtraction()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_bearer_token';
        
        $token = ApiAuth::extractBearerToken();
        $this->assertEquals('test_bearer_token', $token);
    }
}