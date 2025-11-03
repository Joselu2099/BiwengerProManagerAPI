<?php

namespace BiwengerProManagerAPI\Models;

class User implements \JsonSerializable
{
    private $id;
    private $name;
    private $icon;
    private $points;
    private $lastPositions;
    private $position;
    private $positionInc;
    private $role;
    private $account;
    private $balance;
    private $teamValue;
    /** @var Player[] */
    private $players = [];
    private $nmClausesDone = 0; // number of clauses consumed this week (or period)
    private $timesBeenClaused = 0; // times this user has been clausulated

    public function __construct($id, $name, $icon = '', $points = 0, array $lastPositions = [], $position = 0, $positionInc = null, $role = '', $account = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->points = $points;
        $this->lastPositions = $lastPositions;
        $this->position = $position;
        $this->positionInc = $positionInc;
        $this->role = $role;
        $this->balance = 0;
        $this->teamValue = 0;
        $this->players = [];
        $this->account = $account;
    }

    // Legacy constructor for backward compatibility
    public static function createLegacy($id, $name, $balance = 0, $teamValue = 0, array $players = []): self
    {
        $user = new self($id, $name);
        $user->balance = $balance;
        $user->teamValue = $teamValue;
        $user->players = $players;
        return $user;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'points' => $this->points,
            'lastPositions' => $this->lastPositions,
            'position' => $this->position,
            'positionInc' => $this->positionInc,
            'role' => $this->role,
            'balance' => $this->balance,
            'teamValue' => $this->teamValue,
            'players' => array_map(function ($p) {
                return $p instanceof Player ? $p->jsonSerialize() : $p;
            }, $this->players),
            'nmClausesDone' => $this->nmClausesDone,
            'timesBeenClaused' => $this->timesBeenClaused,
            // account may be null or an Account object; expose account_id safely
            'account_id' => ($this->account && method_exists($this->account, 'getId')) ? $this->account->getId() : ($this->account ?? null)
        ];
    }

    // --- Getters for new properties ---
    public function getId()
    {
        return $this->id;
    }
    public function getName()
    {
        return $this->name;
    }
    public function getIcon()
    {
        return $this->icon;
    }
    public function getPoints()
    {
        return $this->points;
    }
    public function getLastPositions()
    {
        return $this->lastPositions;
    }
    public function getPosition()
    {
        return $this->position;
    }
    public function getPositionInc()
    {
        return $this->positionInc;
    }
    public function getRole()
    {
        return $this->role;
    }

    // --- Setters for new properties ---
    public function setIcon(string $icon)
    {
        $this->icon = $icon;
    }
    public function setPoints(int $points)
    {
        $this->points = $points;
    }
    public function setLastPositions(array $lastPositions)
    {
        $this->lastPositions = $lastPositions;
    }
    public function setPosition(int $position)
    {
        $this->position = $position;
    }
    public function setPositionInc(?int $positionInc)
    {
        $this->positionInc = $positionInc;
    }
    public function setRole(string $role)
    {
        $this->role = $role;
    }

    // --- Legacy getters/setters for backward compatibility ---
    public function getBalance()
    {
        return $this->balance;
    }
    public function setBalance($balance)
    {
        $this->balance = $balance;
    }
    public function getTeamValue()
    {
        return $this->teamValue;
    }
    public function setTeamValue($teamValue)
    {
        $this->teamValue = $teamValue;
    }
    public function getAccount()
    {
        return $this->account;
    }
    public function setAccount($account)
    {
        $this->account = $account;
    }
    public function getPlayers()
    {
        return $this->players;
    }
    public function setPlayers(array $players)
    {
        $this->players = $players;
    }

    public function getNmClausesDone()
    {
        return $this->nmClausesDone;
    }
    public function setNmClausesDone(int $v)
    {
        $this->nmClausesDone = $v;
    }
    public function addClauseConsumed()
    {
        $this->nmClausesDone++;
    }

    public function getTimesBeenClaused()
    {
        return $this->timesBeenClaused;
    }
    public function setTimesBeenClaused(int $v)
    {
        $this->timesBeenClaused = $v;
    }
    public function addTimeBeenClaused()
    {
        $this->timesBeenClaused++;
    }

    /**
     * Decide if user can place a clause based on configured limits.
     * This is a simplified parity implementation: it uses provided limits
     * (timesCanClause per period and optionally fallback logic).
     */
    public function canClausule(int $timesCanClause, int $maxTimesBeenClaused): bool
    {
        if ($this->nmClausesDone < $timesCanClause) return true;
        return $this->nmClausesDone < $this->timesBeenClaused && $this->timesBeenClaused < $maxTimesBeenClaused;
    }

    public function hasPlayer($playerId): bool
    {
        foreach ($this->players as $p) {
            if ($p instanceof Player) {
                $playerData = $p->jsonSerialize();
                if (is_array($playerData) && isset($playerData['id']) && (string)$playerData['id'] === (string)$playerId) {
                    return true;
                }
            }
        }
        return false;
    }
}
