<?php

namespace BiwengerProManagerAPI\Services {
    // Global state for mock cURL functions
    $mockCurlOptions = [];
    $mockCurlResponses = [];
    $mockCurlErrors = [];
    $mockCurlErrno = [];
    $mockCurlInfo = [];
    $mockCurlCalledUrls = [];
    $mockCurlHeaders = [];
    $mockCurlPostData = [];
    $mockCurlMethods = [];

    function get_ch_id($ch) {
        return is_object($ch) ? spl_object_id($ch) : (int)$ch;
    }

    function curl_init($url = null) {
        global $mockCurlOptions, $mockCurlCalledUrls;
        $ch = curl_init_original();
        $id = get_ch_id($ch);
        $mockCurlOptions[$id] = [];
        if ($url !== null) {
            $mockCurlOptions[$id][CURLOPT_URL] = $url;
            $mockCurlCalledUrls[] = $url;
        }
        return $ch;
    }

    function curl_setopt($ch, $opt, $val) {
        global $mockCurlOptions, $mockCurlCalledUrls, $mockCurlHeaders, $mockCurlPostData, $mockCurlMethods;
        $id = get_ch_id($ch);
        $mockCurlOptions[$id][$opt] = $val;

        if ($opt === CURLOPT_URL) {
            $mockCurlCalledUrls[] = $val;
        }
        if ($opt === CURLOPT_HTTPHEADER) {
            $mockCurlHeaders[] = $val;
        }
        if ($opt === CURLOPT_POSTFIELDS) {
            $mockCurlPostData[] = $val;
        }
        if ($opt === CURLOPT_CUSTOMREQUEST) {
            $mockCurlMethods[] = $val;
        }

        return curl_setopt_original($ch, $opt, $val);
    }

    function curl_exec($ch) {
        global $mockCurlResponses, $mockCurlOptions;
        $id = get_ch_id($ch);
        $url = $mockCurlOptions[$id][CURLOPT_URL] ?? null;

        // Find if any key matches the start of the URL (for endpoints with dynamic ids/query params)
        foreach ($mockCurlResponses as $k => $v) {
            if ($k !== 'default' && strpos((string)$url, (string)$k) === 0) {
                return $v;
            }
        }

        if (isset($mockCurlResponses['default'])) {
            return $mockCurlResponses['default'];
        }

        return null;
    }

    function curl_errno($ch) {
        global $mockCurlErrno, $mockCurlOptions;
        $id = get_ch_id($ch);
        $url = $mockCurlOptions[$id][CURLOPT_URL] ?? null;

        foreach ($mockCurlErrno as $k => $v) {
            if ($k !== 'default' && strpos((string)$url, (string)$k) === 0) {
                return $v;
            }
        }

        return $mockCurlErrno['default'] ?? 0;
    }

    function curl_error($ch) {
        global $mockCurlErrors, $mockCurlOptions;
        $id = get_ch_id($ch);
        $url = $mockCurlOptions[$id][CURLOPT_URL] ?? null;

        foreach ($mockCurlErrors as $k => $v) {
            if ($k !== 'default' && strpos((string)$url, (string)$k) === 0) {
                return $v;
            }
        }

        return $mockCurlErrors['default'] ?? '';
    }

    function curl_close($ch) {
        return curl_close_original($ch);
    }

    function curl_getinfo($ch, $opt = null) {
        global $mockCurlInfo;
        $id = get_ch_id($ch);
        if ($opt !== null) {
            return $mockCurlInfo[$id][$opt] ?? null;
        }
        return $mockCurlInfo[$id] ?? [];
    }

    // Capture the original global functions using variables
    function curl_init_original($url = null) { return \curl_init($url); }
    function curl_setopt_original($ch, $opt, $val) { return \curl_setopt($ch, $opt, $val); }
    function curl_close_original($ch) { return \curl_close($ch); }
}

namespace Tests\Services {
    use PHPUnit\Framework\TestCase;
    use BiwengerProManagerAPI\Services\BiwengerClient;
    use BiwengerProManagerAPI\Config\Config;

    class BiwengerClientTest extends TestCase
    {
        private $client;

        protected function setUp(): void
        {
            $this->client = new BiwengerClient();

            // Set dummy config for tests to prevent Config issues if any
            putenv('BOT_EMAIL=bot@example.com');
            putenv('BOT_PASSWORD=botpass');
            Config::load();

            // Reset mock state
            global $mockCurlOptions, $mockCurlResponses, $mockCurlErrors, $mockCurlErrno, $mockCurlCalledUrls, $mockCurlHeaders, $mockCurlPostData, $mockCurlMethods;
            $mockCurlOptions = [];
            $mockCurlResponses = [];
            $mockCurlErrors = [];
            $mockCurlErrno = [];
            $mockCurlCalledUrls = [];
            $mockCurlHeaders = [];
            $mockCurlPostData = [];
            $mockCurlMethods = [];

            $mockCurlResponses['default'] = json_encode(['data' => []]);
        }

        // 1. Authentication
        public function testGetTokenValid() {
            global $mockCurlResponses, $mockCurlPostData;
            $mockCurlResponses['https://biwenger.as.com/api/v2/auth/login'] = json_encode(['token' => 'mocked_token']);

            $token = $this->client->getToken('user@example.com', 'password123');
            $this->assertEquals('mocked_token', $token);
            $this->assertEquals('email=user%40example.com&password=password123', $mockCurlPostData[0]);
        }

        public function testGetTokenInvalid() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/auth/login'] = json_encode(['error' => 'invalid credentials']);

            $token = $this->client->getToken('user@example.com', 'wrongpass');
            $this->assertNull($token);
        }

        public function testLogin() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/auth/login'] = json_encode(['token' => 'mocked_token_login']);

            $token = $this->client->login('user@example.com', 'password123');
            $this->assertEquals('mocked_token_login', $token);
        }

        public function testCheckTokenValidityValid() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode(['data' => ['account' => ['id' => 1]]]);

            $isValid = $this->client->checkTokenValidity('valid_token');
            $this->assertTrue($isValid);
        }

        public function testCheckTokenValidityInvalid() {
            global $mockCurlErrno;
            // Simulate curl error to return false
            $mockCurlErrno['https://biwenger.as.com/api/v2/account'] = 28; // timeout error

            $isValid = $this->client->checkTokenValidity('invalid_token');
            $this->assertFalse($isValid);
        }

        public function testCheckTokenValidityNullToken() {
            $isValid = $this->client->checkTokenValidity(null);
            $this->assertFalse($isValid);
        }

        // 2. Competitions Data
        public function testGetPlayersValid() {
            global $mockCurlResponses;
            $mockCurlResponses['https://cf.biwenger.com/api/v2/competitions/la-liga/data'] = json_encode([
                'data' => [
                    'players' => [
                        ['id' => 1, 'name' => 'Player 1'],
                        ['id' => 2, 'name' => 'Player 2']
                    ]
                ]
            ]);

            $players = $this->client->getPlayers('la-liga', 1);
            $this->assertCount(2, $players);
            $this->assertEquals(1, $players[0]['id']);
        }

        public function testGetPlayersNullCompetition() {
            $players = $this->client->getPlayers(null, 1);
            $this->assertEmpty($players);
        }

        public function testGetPlayerById() {
            global $mockCurlResponses;
            $mockCurlResponses['https://cf.biwenger.com/api/v2/competitions/la-liga/data'] = json_encode([
                'data' => [
                    'players' => [
                        ['id' => 100, 'name' => 'Player 100'],
                        ['id' => 200, 'name' => 'Player 200']
                    ]
                ]
            ]);

            $player = $this->client->getPlayerById(200, 'la-liga', 1);
            $this->assertNotNull($player);
            $this->assertEquals('Player 200', $player['name']);

            $notFound = $this->client->getPlayerById(999, 'la-liga', 1);
            $this->assertNull($notFound);
        }

        // 3. Leagues
        public function testGetLeaguesValid() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode([
                'data' => [
                    'leagues' => [
                        ['id' => 1, 'name' => 'League 1'],
                        ['id' => 2, 'name' => 'League 2']
                    ]
                ]
            ]);

            $leagues = $this->client->getLeagues('valid_token');
            $this->assertCount(2, $leagues);
        }

        public function testGetLeaguesNullToken() {
            $leagues = $this->client->getLeagues(null);
            $this->assertEmpty($leagues);
        }

        public function testGetLeagueById() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode([
                'data' => [
                    'leagues' => [
                        ['id' => 10, 'name' => 'League 10'],
                        ['id' => 20, 'name' => 'League 20']
                    ]
                ]
            ]);

            $league = $this->client->getLeagueById(20, 'token');
            $this->assertNotNull($league);
            $this->assertEquals('League 20', $league['name']);

            $notFound = $this->client->getLeagueById(99, 'token');
            $this->assertNull($notFound);
        }

        // 4. Account
        public function testGetAccountValid() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode([
                'data' => [
                    'account' => ['id' => 500, 'name' => 'My Account']
                ]
            ]);

            $account = $this->client->getAccount('token');
            $this->assertNotNull($account);
            $this->assertEquals(500, $account['id']);
        }

        public function testGetAccountCurlError() {
            global $mockCurlResponses;
            // Force curlGet to return null by sending invalid response not processed correctly, actually curl error
            global $mockCurlErrno;
            $mockCurlErrno['https://biwenger.as.com/api/v2/account'] = 6; // Couldn't resolve host

            $account = $this->client->getAccount('token');
            $this->assertNull($account);
        }

        // 5. League Users
        public function testGetUsersOfLeague() {
            global $mockCurlResponses, $mockCurlHeaders;
            $mockCurlResponses['https://biwenger.as.com/api/v2/league'] = json_encode([
                'data' => [
                    'standings' => [
                        ['id' => 1, 'name' => 'User 1'],
                        ['id' => 2, 'name' => 'User 2']
                    ]
                ]
            ]);

            $users = $this->client->getUsersOfLeague('token', 'lg1', 'u1');
            $this->assertCount(2, $users);

            // Check headers
            $hasXLeague = false;
            foreach ($mockCurlHeaders as $headerGroup) {
                if (is_array($headerGroup)) {
                    foreach ($headerGroup as $h) {
                        if (strpos($h, 'x-league: lg1') !== false) $hasXLeague = true;
                    }
                }
            }
            $this->assertTrue($hasXLeague);
        }

        public function testGetUsersOfLeagueNullToken() {
            $users = $this->client->getUsersOfLeague(null, 'lg', 'u');
            $this->assertEmpty($users);
        }

        public function testGetPlayersOfUser() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/user/u1'] = json_encode([
                'data' => [
                    'players' => [
                        ['id' => 1, 'owner' => 'u1'],
                        ['id' => 2, 'owner' => 'u1']
                    ]
                ]
            ]);

            $players = $this->client->getPlayersOfUser('token', 'lg1', 'u1');
            $this->assertCount(2, $players);
        }

        // 6. Transfers and Clauses
        public function testTransferPlayer() {
            global $mockCurlResponses, $mockCurlPostData;

            // Setup getToken mock inside transferPlayer
            $mockCurlResponses['https://biwenger.as.com/api/v2/auth/login'] = json_encode(['token' => 'bot_token']);
            // Setup getIDForBOT mock
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode([
                'data' => [
                    'leagues' => [
                        ['id' => 'lg1', 'user' => ['id' => 'bot_user_id']]
                    ]
                ]
            ]);
            // Setup transfer mock
            $mockCurlResponses['https://biwenger.as.com/api/v2/league/lg1/transfer'] = json_encode([
                'userMessage' => 'Transfer successful'
            ]);

            $data = ['leagueId' => 'lg1', 'userId' => 'u1', 'player' => 100, 'amount' => 5000000];
            $msg = $this->client->transferPlayer($data);

            $this->assertEquals('Transfer successful', $msg);

            // Verify POST data contains encoded json
            $foundPost = false;
            foreach ($mockCurlPostData as $pd) {
                if (is_string($pd) && strpos($pd, '"player":100') !== false) {
                    $foundPost = true;
                }
            }
            $this->assertTrue($foundPost);
        }

        public function testClausePlayer() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/offers'] = json_encode([
                'status' => 200,
                'message' => 'Success',
                'userMessage' => 'Clause paid successfully',
                'code' => 1
            ]);

            $data = ['player' => 100, 'amount' => 10000000];
            $result = $this->client->clausePlayer($data, 'token', 'lg1', 'u1');

            $this->assertEquals(200, $result['status']);
            $this->assertEquals('Clause paid successfully', $result['userMessage']);
        }

        public function testGetIDForBOT() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/account'] = json_encode([
                'data' => [
                    'leagues' => [
                        ['id' => 'target_league', 'user' => ['id' => 'bot_id_123']],
                        ['id' => 'other_league', 'user' => ['id' => 'bot_id_456']]
                    ]
                ]
            ]);

            $id = $this->client->getIDForBOT('token', 'target_league');
            $this->assertEquals('bot_id_123', $id);

            $idNotFound = $this->client->getIDForBOT('token', 'unknown_league');
            $this->assertNull($idNotFound);
        }

        // 7. Rounds
        public function testGetRounds() {
            global $mockCurlResponses;
            $mockCurlResponses['https://cf.biwenger.com/api/v2/competitions/la-liga/season'] = json_encode([
                'data' => [
                    'rounds' => [
                        ['id' => 1, 'name' => 'Jornada 1'],
                        ['id' => 2, 'name' => 'Jornada 2']
                    ]
                ]
            ]);

            $rounds = $this->client->getRounds();
            $this->assertCount(2, $rounds);
            $this->assertEquals('Jornada 1', $rounds[0]['name']);
        }

        public function testGetRoundsResult() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/league/lg1/board?type=roundFinished'] = json_encode([
                'data' => [
                    ['id' => 1, 'type' => 'roundFinished'],
                    ['id' => 2, 'type' => 'roundFinished']
                ]
            ]);

            $results = $this->client->getRoundsResult('token', 'lg1', 'u1');
            $this->assertCount(2, $results);
        }

        // 8. Transfers History
        public function testGetTransfers() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/league/lg1/board?type=transfer'] = json_encode([
                'data' => [
                    ['id' => 1, 'type' => 'transfer'],
                    ['id' => 2, 'type' => 'market']
                ]
            ]);

            $transfers = $this->client->getTransfers('token', 'lg1', 'u1');
            $this->assertCount(2, $transfers);
        }

        // 9. Market
        public function testGetMarketData() {
            global $mockCurlResponses;
            $mockCurlResponses['https://biwenger.as.com/api/v2/user'] = json_encode([
                'data' => [
                    'market' => ['some_market_data']
                ]
            ]);

            $market = $this->client->getMarketData('token', 'lg1', 'u1');
            $this->assertNotNull($market);
            $this->assertArrayHasKey('market', $market);
        }

        // 10. Lineups
        public function testSetLineUp() {
            global $mockCurlResponses, $mockCurlMethods;
            $mockCurlResponses['https://biwenger.as.com/api/v2/user'] = json_encode(['success' => true]);

            $payload = json_encode(['lineup' => [1,2,3]]);
            $response = $this->client->setLineUp($payload, 'token', 'lg1', 'u1');

            $this->assertEquals(json_encode(['success' => true]), $response);
            $this->assertContains('PUT', $mockCurlMethods);
        }

        // 11. Utilities
        public function testExistImg() {
            global $mockCurlResponses;
            $mockCurlResponses['https://example.com/image.png'] = 'binary_image_data';

            $exists = $this->client->existImg('https://example.com/image.png');
            $this->assertTrue($exists);
        }

        public function testExistImgFalse() {
            global $mockCurlResponses;
            $mockCurlResponses['https://example.com/missing.png'] = ''; // Empty response

            $exists = $this->client->existImg('https://example.com/missing.png');
            $this->assertFalse($exists);
        }
    }
}
