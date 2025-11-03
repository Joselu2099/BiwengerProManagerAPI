<?php

namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Database\LeaguesRepository;
use BiwengerProManagerAPI\Models\User;
use BiwengerProManagerAPI\Services\PlayerService;
use BiwengerProManagerAPI\Services\AccountService;
use BiwengerProManagerAPI\Database\ClausulazosRepository;
use BiwengerProManagerAPI\Database\UsersRepository;
use BiwengerProManagerAPI\Utils\Utils;
use BiwengerProManagerAPI\Utils\Logger;

class UsersService
{
    private $client;
    private $playerService;
    private $accountService;
    private $leaguesRepo;
    private $clausRepo;
    private $usersRepo;

    public function __construct(BiwengerClient $client, PlayerService $playerService, AccountService $accountService, LeaguesRepository $leaguesRepo, UsersRepository $usersRepo, ClausulazosRepository $clausRepo = null)
    {
        $this->client = $client;
        $this->playerService = $playerService;
        $this->accountService = $accountService;
        $this->leaguesRepo = $leaguesRepo;
        $this->usersRepo = $usersRepo;
        $this->clausRepo = $clausRepo;
    }

    public function getAll($token = null, $leagueID = null): array
    {
        Logger::info('getAll called: token=' . ($token ? '***' : 'null') . ', leagueID=' . ($leagueID ?? 'null'));
        // Validate token and league basic shape
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        if ($leagueID !== null) {
            $leagueID = trim((string)$leagueID);
            if ($leagueID === '' || strlen($leagueID) > 64) throw new \InvalidArgumentException('invalid league');
        }

        // Try to get xUser from database first
        $xUser = null;
        $usersFromDb = [];
        if ($leagueID !== null) {
            $usersFromDb = $this->usersRepo->getUsersByLeague($leagueID);
            Logger::info('getAll: usersFromDb count=' . count($usersFromDb));
            if (!empty($usersFromDb)) {
                // If we have users in DB, try to get xUser from one of them
                $xUser = $usersFromDb[0]->getId();
                Logger::info('getAll: xUser resolved from DB=' . $xUser);
            }
        }

        // If not found in DB, try to get xUser from league membership entries
        try {
            $league = $this->client->getLeagueById($leagueID, $token);
            Logger::info('getAll: fetched league context');
            if (isset($league['user']['id'])) {
                $xUser = $league['user']['id'];
                Logger::info('getAll: xUser resolved from league=' . $xUser);
            }
        } catch (\Exception $e) {
            Logger::error('getAll: Error getting user context: ' . $e->getMessage());
        }

        // Ensure we pass the league identifier (string/int) to the client, not the full league array
        $raw = $this->client->getUsersOfLeague($token, $leagueID, $xUser);
        $rawCount = (is_array($raw) ? count($raw) : 0);
        Logger::info('getAll: League=' . ($leagueID ?? 'null') . ', xUser=' . ($xUser ?? 'null') . ', Raw response count=' . $rawCount);
        if (empty($raw)) {
            Logger::error('getAll: Empty response from getUsersOfLeague');
        }

        $result = [];

        foreach ($raw as $r) {
            // Map raw standings data to User model
            $user = $this->mapStandingToUser($r);
            if ($user === null) continue;

            Logger::info('getAll: mapped user id=' . $user->getId());

            // Try to resolve the Account model for this request.
            if ($this->accountService) {
                $account = $this->accountService->getAccountByToken($token);
                if (!$account) {
                    // Fallback: fetch from remote API and persist/convert to Account model
                    try {
                        $accData = $this->client->getAccount($token);
                        if (is_array($accData) && !empty($accData)) {
                            // preserve token when persisting the account locally
                            $accData['token'] = $token;
                            $account = $this->accountService->processAccountData($accData);
                        }
                    } catch (\Throwable $e) {
                        // ignore failed remote account fetch
                    }
                }

                if ($account) {
                    $user->setAccount($account);
                }
            }

            // Fetch player's list for this user (for legacy compatibility)
            // Use the league identifier (leagueID) rather than the full league array
            $players = $this->getPlayersOfUser($user->getId(), $token, $leagueID);
            $user->setPlayers($players);

            // Estimate team value as sum of player prices when available
            $teamValue = 0;
            foreach ($players as $p) {
                if (is_object($p) && method_exists($p, 'jsonSerialize')) {
                    $pdata = $p->jsonSerialize();
                    $teamValue += isset($pdata['price']) ? (float)$pdata['price'] : 0;
                }
            }
            $user->setTeamValue($teamValue);

            // Populate clause counters from clausRepo if available
            if ($this->clausRepo !== null) {
                $week = Utils::weekKey();
                try {
                    $nm = $this->clausRepo->getNmClausesDone($user->getId(), $week);
                    $times = $this->clausRepo->getNmTimesBeingClaused($user->getId(), $week);
                    $user->setNmClausesDone((int)$nm);
                    $user->setTimesBeenClaused((int)$times);
                } catch (\Throwable $e) {
                    // ignore DB errors for now
                }
            }

            // Save user to database
            if ($leagueID !== null) {
                $this->usersRepo->saveUser($user, $leagueID);
            }

            $result[] = $user;
        }

        Logger::info('getAll: returning ' . count($result) . ' users');

        return $result;
    }

    public function getPlayersOfUser($userId, $token = null, $leagueId = null): array
    {
    // Avoid array-to-string conversion in logs: serialize complex leagueId values
    $leagueLog = is_scalar($leagueId) ? (string)$leagueId : @json_encode($leagueId);
    Logger::info('getPlayersOfUser called: userId=' . (int)$userId . ', leagueId=' . ($leagueLog ?? 'null'));
        // Validate inputs
        if (!is_numeric($userId) || (int)$userId <= 0) return [];
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        if ($leagueId !== null) {
            $leagueId = trim((string)$leagueId);
            if ($leagueId === '' || strlen($leagueId) > 64) throw new \InvalidArgumentException('invalid league');
        }

        // BiwengerClient expects (token, xLeague, xUser)
        $raw = $this->client->getPlayersOfUser($token, $leagueId, (int)$userId);
        $result = [];
        $rawCount = is_array($raw) ? count($raw) : 0;
    Logger::info('getPlayersOfUser: raw count=' . $rawCount);
        foreach ($raw as $r) {
            try {
                if (!is_array($r)) {
                    Logger::info('getPlayersOfUser: non-array player entry, preserving raw');
                    $result[] = $r;
                    continue;
                }

                // If the player payload already contains details, map directly.
                $hasDetail = isset($r['name']) || isset($r['team']) || isset($r['teamID']) || isset($r['price']) || isset($r['points']) || isset($r['position']);
                if ($hasDetail) {
                    $player = $this->playerService->mapFromArray($r);
                    $pid = (is_object($player) && method_exists($player, 'jsonSerialize')) ? ($player->jsonSerialize()['id'] ?? 'null') : 'null';
                    Logger::info('getPlayersOfUser: mapped detailed player id=' . $pid);
                    $result[] = $player;
                    continue;
                }

                // Fallback: we only have minimal info (id + owner). Fetch full
                // player by id using PlayerService::getById when possible.
                if (isset($r['id']) && is_numeric($r['id'])) {
                    $playerId = (int)$r['id'];
                    Logger::info('getPlayersOfUser: fetching full player by id=' . $playerId);
                    $player = $this->playerService->getById($playerId, null, null, $leagueId);
                    if ($player !== null) {
                        $result[] = $player;
                        continue;
                    }
                    Logger::error('getPlayersOfUser: getById returned null for id=' . $playerId);
                }

                // Last resort: try to map whatever we have (will create a minimal Player)
                $fallback = $this->playerService->mapFromArray($r);
                $fid = (is_object($fallback) && method_exists($fallback, 'jsonSerialize')) ? ($fallback->jsonSerialize()['id'] ?? 'null') : 'null';
                Logger::info('getPlayersOfUser: fallback mapped minimal player id=' . $fid);
                $result[] = $fallback;
            } catch (\Throwable $e) {
                // If mapping fails, fallback to raw array so caller can inspect
                Logger::error('getPlayersOfUser: mapping exception: ' . $e->getMessage());
                $result[] = $r;
            }
        }

        Logger::info('getPlayersOfUser: returning ' . count($result) . ' players for userId=' . (int)$userId);

        return $result;
    }

    /**
     * Get a specific user by ID and league
     */
    public function getUser($userId, $league): ?User
    {
        if ($league !== null) {
            $league = trim((string)$league);
        }

        return $this->usersRepo->getUser((string)$userId, $league);
    }

    /**
     * Update a user's data
     */
    public function updateUser(User $user, $league): bool
    {
        if ($league !== null) {
            $league = trim((string)$league);
            if ($league === '' || strlen($league) > 64) throw new \InvalidArgumentException('invalid league');
        }

        return $this->usersRepo->updateUser($user, $league);
    }

    /**
     * Sync users from API to database
     */
    public function syncUsersFromApi($token, $league): bool
    {
        Logger::info('syncUsersFromApi called: league=' . ($league ?? 'null'));
        if ($token !== null && !is_string($token)) throw new \InvalidArgumentException('invalid token');
        if ($league !== null) {
            $league = trim((string)$league);
            if ($league === '' || strlen($league) > 64) throw new \InvalidArgumentException('invalid league');
        }

        // Get user context for the API call. Resolve xUser only from league membership entries.
        $xUser = null;
        if ($token && $league) {
            try {
                $leagues = $this->client->getLeagues($token);
                foreach ($leagues as $l) {
                    if ((string)($l['id'] ?? '') === (string)$league) {
                        if (isset($l['user']['id'])) {
                            $xUser = $l['user']['id'];
                        }
                        break;
                    }
                }
            } catch (\Exception $e) {
                error_log("Error getting user context for sync: " . $e->getMessage());
            }
        }

        $raw = $this->client->getUsersOfLeague($token, $league, $xUser);
        Logger::info('syncUsersFromApi: fetched ' . (is_array($raw) ? count($raw) : 0) . ' entries');
        $res = $this->usersRepo->syncUsersFromApi($raw, $league);
        Logger::info('syncUsersFromApi: sync result=' . ($res ? 'true' : 'false'));
        return $res;
    }

    /**
     * Map raw standing data from Biwenger API to User model
     */
    private function mapStandingToUser(array $standing): ?User
    {
        try {
            return new User(
                $standing['id'] ?? null,
                $standing['name'] ?? '',
                $standing['icon'] ?? '',
                $standing['points'] ?? 0,
                $standing['lastPositions'] ?? [],
                $standing['position'] ?? 0,
                $standing['positionInc'] ?? null,
                $standing['role'] ?? ''
            );
        } catch (\Exception $e) {
            Logger::error("Error mapping standing to user: " . $e->getMessage());
            return null;
        }
    }
}
