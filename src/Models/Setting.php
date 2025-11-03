<?php
namespace BiwengerProManagerAPI\Models;

class Setting implements \JsonSerializable
{
    // Biwenger settings from API
    private $secret;
    private $privacy;
    private $onlyAdminPosts;
    private $clause;
    private $clauseIncrement;
    private $immediateSales;
    private $balance;
    private $userOffers;
    private $loans;
    private $loansMinRounds;
    private $loansMaxRounds;
    private $maxPurchasePrice;
    private $challengesAllow;
    private $roundDelayed;
    private $marketShowBids;
    private $lineupMultiPos;
    private $lineupAllowExtra;
    private $lineupCoach;
    private $lineupCaptain;
    private $lineupStriker;
    private $lineupChanges;
    private $marketValues;
    private $lineupReserves;
    private $lineupMaxClubPlayers;
    private $favoritesAllow;
    private $auctions;
    private $customScore;

    // Custom settings from our database
    private $clauses;
    private $clauses_value;
    private $times_can_clause;
    private $max_times_claused;
    private $num_rounds_to_unlock;
    private $num_days_before_round;
    private $max_players_same_team;

    public function __construct(array $biwengerSettings = [], array $customSettings = [])
    {
        // Map Biwenger settings
        $this->secret = $biwengerSettings['secret'] ?? null;
        $this->privacy = $biwengerSettings['privacy'] ?? null;
        $this->onlyAdminPosts = $biwengerSettings['onlyAdminPosts'] ?? false;
        $this->clause = $biwengerSettings['clause'] ?? null;
        $this->clauseIncrement = $biwengerSettings['clauseIncrement'] ?? null;
        $this->immediateSales = $biwengerSettings['immediateSales'] ?? null;
        $this->balance = $biwengerSettings['balance'] ?? null;
        $this->userOffers = $biwengerSettings['userOffers'] ?? null;
        $this->loans = $biwengerSettings['loans'] ?? null;
        $this->loansMinRounds = $biwengerSettings['loansMinRounds'] ?? null;
        $this->loansMaxRounds = $biwengerSettings['loansMaxRounds'] ?? null;
        $this->maxPurchasePrice = $biwengerSettings['maxPurchasePrice'] ?? null;
        $this->challengesAllow = $biwengerSettings['challengesAllow'] ?? null;
        $this->roundDelayed = $biwengerSettings['roundDelayed'] ?? null;
        $this->marketShowBids = $biwengerSettings['marketShowBids'] ?? null;
        $this->lineupMultiPos = $biwengerSettings['lineupMultiPos'] ?? null;
        $this->lineupAllowExtra = $biwengerSettings['lineupAllowExtra'] ?? null;
        $this->lineupCoach = $biwengerSettings['lineupCoach'] ?? null;
        $this->lineupCaptain = $biwengerSettings['lineupCaptain'] ?? null;
        $this->lineupStriker = $biwengerSettings['lineupStriker'] ?? null;
        $this->lineupChanges = $biwengerSettings['lineupChanges'] ?? null;
        $this->marketValues = $biwengerSettings['marketValues'] ?? null;
        $this->lineupReserves = $biwengerSettings['lineupReserves'] ?? null;
        $this->lineupMaxClubPlayers = $biwengerSettings['lineupMaxClubPlayers'] ?? null;
        $this->favoritesAllow = $biwengerSettings['favoritesAllow'] ?? null;
        $this->auctions = $biwengerSettings['auctions'] ?? null;
        $this->customScore = $biwengerSettings['customScore'] ?? null;

        // Map custom settings
        $this->clauses = $customSettings['clauses'] ?? false;
        $this->clauses_value = $customSettings['clauses_value'] ?? 0;
        $this->times_can_clause = $customSettings['times_can_clause'] ?? 0;
        $this->max_times_claused = $customSettings['max_times_claused'] ?? 0;
        $this->num_rounds_to_unlock = $customSettings['num_rounds_to_unlock'] ?? 0;
        $this->num_days_before_round = $customSettings['num_days_before_round'] ?? 0;
        $this->max_players_same_team = $customSettings['max_players_same_team'] ?? 0;
    }

    // Getters for Biwenger settings
    public function getSecret() { return $this->secret; }
    public function getPrivacy() { return $this->privacy; }
    public function getOnlyAdminPosts() { return $this->onlyAdminPosts; }
    public function getClause() { return $this->clause; }
    public function getClauseIncrement() { return $this->clauseIncrement; }
    public function getImmediateSales() { return $this->immediateSales; }
    public function getBalance() { return $this->balance; }
    public function getUserOffers() { return $this->userOffers; }
    public function getLoans() { return $this->loans; }
    public function getLoansMinRounds() { return $this->loansMinRounds; }
    public function getLoansMaxRounds() { return $this->loansMaxRounds; }
    public function getMaxPurchasePrice() { return $this->maxPurchasePrice; }
    public function getChallengesAllow() { return $this->challengesAllow; }
    public function getRoundDelayed() { return $this->roundDelayed; }
    public function getMarketShowBids() { return $this->marketShowBids; }
    public function getLineupMultiPos() { return $this->lineupMultiPos; }
    public function getLineupAllowExtra() { return $this->lineupAllowExtra; }
    public function getLineupCoach() { return $this->lineupCoach; }
    public function getLineupCaptain() { return $this->lineupCaptain; }
    public function getLineupStriker() { return $this->lineupStriker; }
    public function getLineupChanges() { return $this->lineupChanges; }
    public function getMarketValues() { return $this->marketValues; }
    public function getLineupReserves() { return $this->lineupReserves; }
    public function getLineupMaxClubPlayers() { return $this->lineupMaxClubPlayers; }
    public function getFavoritesAllow() { return $this->favoritesAllow; }
    public function getAuctions() { return $this->auctions; }
    public function getCustomScore() { return $this->customScore; }

    // Getters for custom settings
    public function getClauses() { return $this->clauses; }
    public function getClausesValue() { return $this->clauses_value; }
    public function getTimesCanClause() { return $this->times_can_clause; }
    public function getMaxTimesClaused() { return $this->max_times_claused; }
    public function getNumRoundsToUnlock() { return $this->num_rounds_to_unlock; }
    public function getNumDaysBeforeRound() { return $this->num_days_before_round; }
    public function getMaxPlayersSameTeam() { return $this->max_players_same_team; }

    public function jsonSerialize(): array
    {
        return [
            // Biwenger settings
            'secret' => $this->secret,
            'privacy' => $this->privacy,
            'onlyAdminPosts' => $this->onlyAdminPosts,
            'clause' => $this->clause,
            'clauseIncrement' => $this->clauseIncrement,
            'immediateSales' => $this->immediateSales,
            'balance' => $this->balance,
            'userOffers' => $this->userOffers,
            'loans' => $this->loans,
            'loansMinRounds' => $this->loansMinRounds,
            'loansMaxRounds' => $this->loansMaxRounds,
            'maxPurchasePrice' => $this->maxPurchasePrice,
            'challengesAllow' => $this->challengesAllow,
            'roundDelayed' => $this->roundDelayed,
            'marketShowBids' => $this->marketShowBids,
            'lineupMultiPos' => $this->lineupMultiPos,
            'lineupAllowExtra' => $this->lineupAllowExtra,
            'lineupCoach' => $this->lineupCoach,
            'lineupCaptain' => $this->lineupCaptain,
            'lineupStriker' => $this->lineupStriker,
            'lineupChanges' => $this->lineupChanges,
            'marketValues' => $this->marketValues,
            'lineupReserves' => $this->lineupReserves,
            'lineupMaxClubPlayers' => $this->lineupMaxClubPlayers,
            'favoritesAllow' => $this->favoritesAllow,
            'auctions' => $this->auctions,
            'customScore' => $this->customScore,
            
            // Custom settings
            'clauses' => $this->clauses,
            'clauses_value' => $this->clauses_value,
            'times_can_clause' => $this->times_can_clause,
            'max_times_claused' => $this->max_times_claused,
            'num_rounds_to_unlock' => $this->num_rounds_to_unlock,
            'num_days_before_round' => $this->num_days_before_round,
            'max_players_same_team' => $this->max_players_same_team
        ];
    }
}