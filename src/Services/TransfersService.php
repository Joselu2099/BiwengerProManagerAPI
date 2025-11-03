<?php
namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Database\ClausulazosRepository;
use BiwengerProManagerAPI\Utils\Utils;
use BiwengerProManagerAPI\Utils\Logger;

class TransfersService
{
    private $client;
    private $clausRepo;

    public function __construct(BiwengerClient $client, ClausulazosRepository $clausRepo = null)
    {
        $this->client = $client;
        $this->clausRepo = $clausRepo;
    }

    /**
     * Transfer a player between users inside the configured league.
     * $data must match Biwenger API payload expected in original project.
     */
    public function transferPlayer(array $data): string
    {
        // Validate payload defensively
        $leagueId = $data['leagueId'] ?? $data['x_league'] ?? $data['xLeague'] ?? null;
        if (empty($leagueId)) throw new \InvalidArgumentException('leagueId is required');
        if (!isset($data['playerId']) || !is_numeric($data['playerId']) || (int)$data['playerId'] <= 0) throw new \InvalidArgumentException('invalid playerId');
        if (!isset($data['fromUserId']) || !is_numeric($data['fromUserId']) || (int)$data['fromUserId'] <= 0) throw new \InvalidArgumentException('invalid fromUserId');
        if (!isset($data['toUserId']) || !is_numeric($data['toUserId']) || (int)$data['toUserId'] <= 0) throw new \InvalidArgumentException('invalid toUserId');

        // Optional: sanitize strings to avoid unexpected payload sizes
        if (isset($data['notes'])) {
            $data['notes'] = substr((string)$data['notes'], 0, 1000);
        }

        Logger::info('TransfersService: transferPlayer called for league=' . ($data['leagueId'] ?? $data['x_league'] ?? 'unknown') . ' playerId=' . ($data['playerId'] ?? 'unknown'));
        $res = $this->client->transferPlayer($data);
        Logger::info('TransfersService: transferPlayer response=' . substr((string)$res, 0, 200));
        return $res;
    }

    /**
     * Place a clause/offer for a player. Persists the clausulazo to DB when repository available.
     * Returns Biwenger response normalized.
     */
    public function clausePlayer(array $data, $token = null, $xLeague = null, $xUser = null): array
    {
        // Validate basic payload
        if (!isset($data['playerId']) || !is_numeric($data['playerId']) || (int)$data['playerId'] <= 0) throw new \InvalidArgumentException('invalid playerId');
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] < 0) throw new \InvalidArgumentException('invalid amount');

        // Delegate to client with explicit token/xLeague/xUser
    Logger::info('TransfersService: clausePlayer called playerId=' . ($data['playerId'] ?? 'unknown') . ' amount=' . ($data['amount'] ?? 'unknown'));
    $res = $this->client->clausePlayer($data, $token, $xLeague, $xUser);
    Logger::info('TransfersService: clausePlayer client response status=' . ($res['status'] ?? 'n/a'));

        // If insertion repo available and response seems successful, persist record
        try {
            $ok = false;
            if (is_array($res) && isset($res['status'])) {
                $status = (int)$res['status'];
                $ok = $status >= 200 && $status < 400;
            } else {
                // Fallback: if code exists and is 0 treat as success
                if (isset($res['code']) && (int)$res['code'] === 0) $ok = true;
            }

            if ($ok && $this->clausRepo !== null) {
                $userFrom = $data['fromUserName'] ?? ($data['fromUserId'] ?? '');
                $userTo = $data['toUserName'] ?? ($data['toUserId'] ?? '');
                $playerId = $data['playerId'] ?? ($data['player']['id'] ?? null);
                $playerName = $data['playerName'] ?? ($data['player']['name'] ?? null);
                $amount = $data['amount'] ?? 0;
                $date = $data['date'] ?? date('c');
                $week = Utils::weekKey($date);
                $this->clausRepo->insertClausulazo($data['fromUserId'] ?? null, $userFrom, $data['toUserId'] ?? null, $userTo, $playerId, $playerName, $amount, $date, $week);
                Logger::info('TransfersService: persisted clausulazo playerId=' . $playerId . ' from=' . ($data['fromUserId'] ?? 'n/a') . ' to=' . ($data['toUserId'] ?? 'n/a'));
            }
        } catch (\Throwable $e) {
            // Do not break the API if DB persistence fails; just log
            Logger::error('TransfersService: Failed to persist clausulazo: ' . $e->getMessage());
        }

        return $res;
    }
}
