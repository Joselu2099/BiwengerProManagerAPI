<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Utils\Logger;
use BiwengerProManagerAPI\Config\Config;

/**
 * Client for the public Biwenger API.
 * This implementation adapts the logic from the original `BiwengerAPI.php` and
 * keeps compatibility with the calls used by the original project.
 *
 * Behavior:
 * - Uses internal properties (`token`, `x_league`, `x_user`, `competition`) when set,
 *   and falls back to `$_SESSION[...]` when not.
 * - Exposes convenience methods for authentication (`login`/`getToken`) that mirror
 *   the original project's calls.
 */
class BiwengerClient
{
    private const BASE_URL = 'https://biwenger.as.com/api/';
    private const CF_BASE_URL = 'https://cf.biwenger.com/api/';
    private const API_VERSION = 'v2';

    public function __construct(array $config = [])
    {
        // No specific initialization needed for now
    }

    private function curlGet(string $url, array $headers = [])
    {
        $ch = curl_init();
        $timeout = 10;
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        $err = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);
        if ($err) {
            Logger::error("curlGet error ($url): $errMsg");
            return null;
        }
        return $data;
    }

    public function getToken(string $login, string $password)
    {
        $url = self::BASE_URL . self::API_VERSION . '/auth/login';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
        curl_setopt($ch, CURLOPT_POST, 1);
        $fields = ['email' => $login, 'password' => $password];
        $post_fields = http_build_query($fields);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        $result = curl_exec($ch);
        curl_close($ch);
        $jsonArray = json_decode($result, true);
        return $jsonArray['token'] ?? null;
    }

    public function login(string $email, string $password)
    {
        return $this->getToken($email, $password);
    }

    public function getPlayers($competition, $scoreID = 1)
    {
        // In stateless mode, require an explicit competition; return empty list when absent
        if ($competition === null) {
            return [];
        }
        $url = self::CF_BASE_URL . self::API_VERSION . '/competitions/' . $competition . '/data?&score=' . $scoreID;
        $data = $this->curlGet($url);
        if ($data === null) return [];
        $jsonArray = json_decode($data, true);
        return $jsonArray['data']['players'] ?? [];
    }

    /**
     * Return a single player raw array by id or null if not found.
     */
    public function getPlayerById($id, $competition, $scoreID)
    {
        $players = $this->getPlayers($competition, $scoreID);
        foreach ($players as $p) {
            if (!isset($p['id'])) continue;
            if ((string)$p['id'] === (string)$id) return $p;
        }
        return null;
    }

    public function getLeagues($token)
    {
        // If token is not provided, treat as public request and return empty list
        if($token === null) return [];
        $url = self::BASE_URL . self::API_VERSION . '/account';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['Content-Type: application/json', $authorization];
        $data = $this->curlGet($url, $headers);
        if ($data === null) return [];
        $jsonArray = json_decode($data, true);
        // Do not write to session in stateless mode
        return $jsonArray['data']['leagues'] ?? [];
    }

    /**
     * Return a single league raw array by id or null if not found.
     */
    public function getLeagueById($id, $token)
    {
        $leagues = $this->getLeagues($token);
        foreach ($leagues as $l) {
            if (!isset($l['id'])) continue;
            if ((string)$l['id'] === (string)$id) return $l;
        }
        return null;
    }

    public function getAccount(?string $token = null)
    {
        // Do not attempt to read token from session. Require explicit token when needed.
        $token = $token ?? null;
        $url = self::BASE_URL . self::API_VERSION . '/account';
        $authorization = 'Authorization: Bearer ' . $token;
        $data = $this->curlGet($url, ['Content-Type: application/json', $authorization]);
        if ($data === null) return null;
        $jsonArray = json_decode($data, true);
        return $jsonArray['data']['account'] ?? null;
    }

    public function getUsersOfLeague($token, $xLeague, $xUser)
    {
        if ($token === null) return [];
        $url = self::BASE_URL . self::API_VERSION . '/league?include=all,-lastAccess&fields=*,standings,tournaments,group,settings(description)';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['x-league: ' . $xLeague, 'x-user: ' . $xUser, 'Content-Type: application/json', $authorization];
        
        // Debug logging
        Logger::info("getUsersOfLeague: URL=$url, xLeague=$xLeague, xUser=$xUser");
        
        $data = $this->curlGet($url, $headers);
        if ($data === null) {
            Logger::error("getUsersOfLeague: No data returned from API");
            return [];
        }
        
        $jsonArray = json_decode($data, true);
        $standings = $jsonArray['data']['standings'] ?? [];
        
        Logger::info("getUsersOfLeague: Decoded " . count($standings) . " standings");
        
        return $standings;
    }

    public function getPlayersOfUser($token, $xLeague, $xUser)
    {
        // Require explicit token in stateless mode
        if ($token === null) return [];
        $url = self::BASE_URL . self::API_VERSION . '/user/' . $xUser . '?fields=*,account(id),players(id,owner),lineups(round,points,count,position),league(id,name,competition,type,mode,marketMode,scoreID),market,seasons,offers,lastPositions';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['x-league: ' . ($xLeague ?? ''), 'x-user: ' . ($xUser ?? ''), 'Content-Type: application/json', $authorization];
        $data = $this->curlGet($url, $headers);
        if ($data === null) return [];
        $jsonArray = json_decode($data, true);
        return $jsonArray['data']['players'] ?? [];
    }

    public function transferPlayer(array $data)
    {
        $token = $this->getToken(Config::getBotEmail(), Config::getBotPassword());
        // Expect caller to include league id and user ids in payload to remain stateless.
        $leagueId = $data['leagueId'] ?? $data['x_league'] ?? $data['xLeague'] ?? null;
        $userIdHeader = $data['userId'] ?? $data['x_user'] ?? $data['xUser'] ?? $data['fromUserId'] ?? null;
        $url = self::BASE_URL . self::API_VERSION . '/league/' . $leagueId . '/transfer';
        $authorization = 'Authorization: Bearer ' . $token;
        $postdata = json_encode($data);
        Logger::info('transferPlayer: ' . $postdata);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-league: ' . $leagueId, 'x-user: ' . $this->getIDForBOT($token, $leagueId), 'Content-Type: application/json', $authorization]);
        $result = curl_exec($ch);
        curl_close($ch);

        $jsonArray = json_decode($result, true);
        $msg = $jsonArray['userMessage'] ?? '';
        return $msg;
    }

    public function getIDForBOT($token, $leagueID)
    {
        $url = self::BASE_URL . self::API_VERSION . '/account';
        $authorization = 'Authorization: Bearer ' . $token;
        $data = $this->curlGet($url, ['Content-Type: application/json', $authorization]);
        if ($data === null) return null;
        $jsonArray = json_decode($data, true);
        foreach ($jsonArray['data']['leagues'] ?? [] as $league) {
            if (($league['id'] ?? null) == $leagueID) {
                return $league['user']['id'] ?? null;
            }
        }
        return null;
    }

    public function clausePlayer(array $data, $token, $xLeague, $xUser)
    {
        $url = self::BASE_URL . self::API_VERSION . '/offers';
        $authorization = 'Authorization: Bearer ' . $token;
        $postdata = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-league: ' . $xLeague, 'x-user: ' . $xUser, 'Content-Type: application/json', $authorization]);

        $result = curl_exec($ch);
        curl_close($ch);

        $jsonArray = json_decode($result, true);

        return [
            'status' => $jsonArray['status'] ?? 404,
            'message' => $jsonArray['message'] ?? 'No message',
            'userMessage' => $jsonArray['userMessage'] ?? 'No user message',
            'code' => $jsonArray['code'] ?? 0
        ];
    }

    public function checkTokenValidity($token)
    {
        if ($token == null || $token == '') return false;
        $url = self::BASE_URL . self::API_VERSION . '/account';
        $authorization = 'Authorization: Bearer ' . $token;
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $authorization]);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return true;
    }

    public function getRounds()
    {
        $url = self::CF_BASE_URL . self::API_VERSION . '/competitions/la-liga/season';
        $result = $this->curlGet($url);
        if ($result === null) return [];
        $rounds = json_decode($result, true);
        return $rounds['data']['rounds'] ?? [];
    }

    public function getTransfers($token, $xLeague, $xUser)
    {
        $url = self::BASE_URL . self::API_VERSION . '/league/' . $xLeague . '/board?type=transfer,market,loan,loanReturn,adminTransfer&limit=999999';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['x-league: ' . $xLeague, 'x-user: ' . $xUser, 'Content-Type: application/json', $authorization];
        $data = $this->curlGet($url, $headers);
        if ($data === null) return [];
        $jsonArray = json_decode($data, true);
        return $jsonArray['data'] ?? [];
    }

    public function getRoundsResult($token, $xLeague, $xUser)
    {
        $url = self::BASE_URL . self::API_VERSION . '/league/' . $xLeague . '/board?type=roundFinished&limit=999999';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['x-league: ' . $xLeague, 'x-user: ' . $xUser, 'Content-Type: application/json', $authorization];
        $data = $this->curlGet($url, $headers);
        if ($data === null) return [];
        $jsonArray = json_decode($data, true);
        return $jsonArray['data'] ?? [];
    }

    public function getMarketData($token, $x_league, $x_user)
    {
        $url = self::BASE_URL . self::API_VERSION . '/user?fields=*,lineup(type,playersID,reservesID,captain,striker,coach,date),players(id,owner),market,offers,-trophies';
        $authorization = 'Authorization: Bearer ' . $token;
        $headers = ['x-league: ' . $x_league, 'x-user: ' . $x_user, 'Content-Type: application/json', $authorization];
        $data = $this->curlGet($url, $headers);
        if ($data === null) return null;
        $jsonArray = json_decode($data, true);
        return $jsonArray['data'] ?? null;
    }

    public function existImg($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $raw = curl_exec($ch);
        curl_close($ch);
        return !empty($raw);
    }

    public function setLineUp($payload, $token, $xLeague, $xUser)
    {
        $url = self::BASE_URL . self::API_VERSION . '/user?fields=*,lineup(date)';
        $authorization = 'Authorization: Bearer ' . $token;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-league: ' . $xLeague, 'x-user: ' . $xUser, 'Content-Type: application/json', $authorization]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
