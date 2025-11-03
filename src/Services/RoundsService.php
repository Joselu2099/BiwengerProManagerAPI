<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Models\Round;
use BiwengerProManagerAPI\Utils\Logger;

class RoundsService
{
    private $client;

    public function __construct(BiwengerClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $raw = $this->client->getRounds();
        $result = [];
        Logger::info('RoundsService: fetched rounds count=' . (is_array($raw) ? count($raw) : 0));
        foreach ($raw as $r) {
            $result[] = new Round($r['id'] ?? null, $r['name'] ?? null, $r['status'] ?? null, $r['start'] ?? null, $r['end'] ?? null);
        }
        return $result;
    }

    public function getResults($token = null, $league = null): array
    {
        // Validate inputs
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        if ($league !== null) {
            $league = trim((string)$league);
            if ($league === '' || strlen($league) > 64) throw new \InvalidArgumentException('invalid league');
        }

        // Pass token and league when available (client expects token, xLeague, xUser)
        $raw = $this->client->getRoundsResult($token, $league, null);
        Logger::info('RoundsService: getResults fetched count=' . (is_array($raw) ? count($raw) : 0));
        $result = [];
        foreach ($raw as $r) {
            $result[] = $r; // already structured events; controller can decide mapping
        }
        return $result;
    }
}
