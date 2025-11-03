<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Models\Player;
use BiwengerProManagerAPI\Database\LeaguesRepository;
use BiwengerProManagerAPI\Models\League;
use BiwengerProManagerAPI\Utils\Logger;

class PlayerService
{
    private $client;
    private $leaguesRepository;

    public function __construct(BiwengerClient $client, LeaguesRepository $leaguesRepository)
    {
        $this->client = $client;
        $this->leaguesRepository = $leaguesRepository;
    }

    public function getAll($competition = null, $scoreID = null, $leagueId = null): array
    {
        $league = null;
        if ($leagueId !== null) {
            $leagueId = trim((string)$leagueId);
            $league = $this->leaguesRepository->getCompleteLeague($leagueId);
        }

        if ($league !== null) {
            $competition = $league->getCompetition();
            $scoreID = $league->getScoreID();
        } else {
            // Validate competition
            if ($competition !== null) {
                $competition = trim((string)$competition);
                if ($competition === '' || strlen($competition) > 64) throw new \InvalidArgumentException('invalid competition');
            }

            // Validate scoreID
            if ($scoreID !== null) {
                $scoreID = trim((string)$scoreID);
                if ($scoreID === '' || strlen($scoreID) > 64) throw new \InvalidArgumentException('invalid scoreID');
            }
        }

    // BiwengerClient exposes getPlayers/getPlayerById
    Logger::info('PlayerService: getAll called competition=' . ($competition ?? 'null') . ' scoreID=' . ($scoreID ?? 'null') . ' leagueId=' . ($leagueId ?? 'null'));
    $raw = $this->client->getPlayers($competition, $scoreID);

        // Normalize possible return types from the client. The client should
        // return an array of players, but in some error cases it may return
        // null or a string (json-encoded error). Protect against that so we
        // never trigger a PHP warning in foreach and always return an array.
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                // If client returned a wrapped response, attempt to extract players
                $raw = $decoded['data']['players'] ?? [];
            } else {
                // Not a usable payload -> return empty list
                return [];
            }
        }

        if (!is_array($raw) && !is_object($raw)) {
            Logger::error('PlayerService: getPlayers returned unexpected payload');
            return [];
        }
        $result = [];
        Logger::info('PlayerService: raw players count=' . (is_array($raw) ? count($raw) : 0));
        foreach ((array)$raw as $r) {
            // Ensure each item is an array before mapping
            if (!is_array($r)) continue;
            $result[] = $this->mapFromArray($r);
        }
        return $result;
    }

    public function getById(int $id, $competition=null, $scoreID=null, $leagueId=null)
    {
        Logger::info('PlayerService: getById called id=' . $id . ' competition=' . ($competition ?? 'null') . ' scoreID=' . ($scoreID ?? 'null') . ' leagueId=' . ($leagueId ?? 'null'));
        if ($id <= 0) return null;
        $league = null;
        if ($leagueId !== null) {
            $leagueId = trim((string)$leagueId);
            $league = $this->leaguesRepository->getCompleteLeague($leagueId);
        }

        if ($league !== null) {
            $competition = $league->getCompetition();
            $scoreID = $league->getScoreID();
        } else {
            // Validate competition
            if ($competition !== null) {
                $competition = trim((string)$competition);
                if ($competition === '' || strlen($competition) > 64) throw new \InvalidArgumentException('invalid competition');
            }

            // Validate scoreID
            if ($scoreID !== null) {
                $scoreID = trim((string)$scoreID);
                if ($scoreID === '' || strlen($scoreID) > 64) throw new \InvalidArgumentException('invalid scoreID');
            }
        }
        $r = $this->client->getPlayerById($id, $competition, $scoreID);
        if (!$r) {
            Logger::error('PlayerService: player not found id=' . $id);
            return null;
        }
        return $this->mapFromArray($r);
    }

    /**
     * Map a raw player array returned by BiwengerClient into a Player model.
     */
    public function mapFromArray(array $r): Player
    {
        // Some endpoints return players nested under a `player` key or with
        // different naming conventions. Normalize common variants so mapping
        // is tolerant to API shape changes.
        if (isset($r['player']) && is_array($r['player'])) {
            $r = $r['player'];
        }

        // If the payload has numeric-indexed arrays (rare), try to pick first
        if (!isset($r['id']) && isset($r[0]) && is_array($r[0])) {
            $r = $r[0];
        }

        // Helper to read multiple possible keys
        $get = function(array $arr, array $keys, $default = null) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $arr) && $arr[$k] !== null) return $arr[$k];
            }
            return $default;
        };

        $id = $get($r, ['id', 'playerId', 'player_id']);
        $name = $get($r, ['name', 'nombre', 'fullName', 'fullname']);
        $team = $get($r, ['teamID', 'teamId', 'team_id', 'team', 'equipo']);
        $position = $get($r, ['position', 'pos', 'posicion']);
        $price = $get($r, ['price', 'value', 'valor', 'precio'], 0);
        $priceIncrement = $get($r, ['priceIncrement', 'price_change', 'priceChange'], 0);
        $points = $get($r, ['points', 'puntos', 'score'], 0);

        return new Player(
            $id,
            $name,
            $team,
            $position,
            is_numeric($price) ? (float)$price : 0,
            is_numeric($priceIncrement) ? (float)$priceIncrement : 0,
            is_numeric($points) ? (float)$points : 0
        );
    }
}
