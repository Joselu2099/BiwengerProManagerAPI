<?php
namespace BiwengerProManagerAPI\Database;

use MongoDB\BSON\UTCDateTime;
use BiwengerProManagerAPI\Utils\Logger;

class ClausulazosRepository
{
    private $db;
    private $collectionName = 'clausulazos';

    public function __construct($db = null)
    {
        $this->db = $db ?? MongoConnection::getInstance()->getDb();
        $col = $this->db->selectCollection($this->collectionName);
        // create useful indexes
        $col->createIndex(['user_to_id' => 1, 'week' => 1]);
        $col->createIndex(['user_from_id' => 1, 'week' => 1]);
        $col->createIndex(['player_id' => 1, 'date' => 1]);
    }

    public function existClausulazo($user_from_id, $user_to_id, $player_id, $date): bool
    {
        try {
            $d = new \DateTime($date);
            $start = new UTCDateTime((new \DateTime($d->format('Y-m-d') . ' 00:00:00'))->getTimestamp() * 1000);
            $end = new UTCDateTime((new \DateTime($d->format('Y-m-d') . ' 23:59:59'))->getTimestamp() * 1000);
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne([
                'user_from_id' => (int)$user_from_id,
                'user_to_id' => (int)$user_to_id,
                'player_id' => (int)$player_id,
                'date' => ['$gte' => $start, '$lte' => $end]
            ]);
            return $doc !== null;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::existClausulazo failed: ' . $e->getMessage());
            return false;
        }
    }

    public function insertClausulazo($user_from_id, $user_from, $user_to_id, $user_to, $player_id, $player, $amount, $date, $week): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $dt = new \DateTime($date);
            $utc = new UTCDateTime($dt->getTimestamp() * 1000);
            $res = $col->insertOne([
                'user_from_id' => (int)$user_from_id,
                'user_from' => $user_from,
                'user_to_id' => (int)$user_to_id,
                'user_to' => $user_to,
                'player_id' => (int)$player_id,
                'player' => $player,
                'amount' => (int)$amount,
                'date' => $utc,
                'week' => (int)$week,
            ]);
            Logger::info('ClausulazosRepository: insertClausulazo insertedId=' . json_encode($res->getInsertedId()));
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::insertClausulazo failed: ' . $e->getMessage());
        }
    }

    public function getAllClausulazos(): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find();
            $out = [];
            foreach ($cursor as $doc) {
                $arr = (array)$doc;
                $out[] = $this->normalizeDoc($arr);
            }
            Logger::info('ClausulazosRepository: getAllClausulazos count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getAllClausulazos failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getClausulazosByDate($date): array
    {
        try {
            $d = new \DateTime($date);
            $start = new UTCDateTime((new \DateTime($d->format('Y-m-d') . ' 00:00:00'))->getTimestamp() * 1000);
            $end = new UTCDateTime((new \DateTime($d->format('Y-m-d') . ' 23:59:59'))->getTimestamp() * 1000);
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find(['date' => ['$gte' => $start, '$lte' => $end]]);
            $out = [];
            foreach ($cursor as $doc) $out[] = $this->normalizeDoc((array)$doc);
            Logger::info('ClausulazosRepository: getClausulazosByDate date=' . $date . ' count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getClausulazosByDate failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getClausulazosByWeek($week): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find(['week' => (int)$week]);
            $out = [];
            foreach ($cursor as $doc) $out[] = $this->normalizeDoc((array)$doc);
            Logger::info('ClausulazosRepository: getClausulazosByWeek week=' . (int)$week . ' count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getClausulazosByWeek failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getClausulazosByUserLeagueId($user_from_id): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find(['user_from_id' => (int)$user_from_id]);
            $out = [];
            foreach ($cursor as $doc) $out[] = $this->normalizeDoc((array)$doc);
            Logger::info('ClausulazosRepository: getClausulazosByUserLeagueId user_from_id=' . (int)$user_from_id . ' count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getClausulazosByUserLeagueId failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getNmClausesDone($userToId, $week): int
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $count = (int)$col->countDocuments(['user_to_id' => (int)$userToId, 'week' => (int)$week]);
            return $count;
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getNmClausesDone failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function getNmTimesBeingClaused($userFromId, $week): int
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            return (int)$col->countDocuments(['user_from_id' => (int)$userFromId, 'week' => (int)$week]);
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::getNmTimesBeingClaused failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function deleteClausulazoById($id): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $res = $col->deleteOne(['_id' => $id]);
            Logger::info('ClausulazosRepository: deleteClausulazoById id=' . (string)$id . ' deleted=' . $res->getDeletedCount());
        } catch (\Throwable $e) {
            Logger::error('ClausulazosRepository::deleteClausulazoById failed: ' . $e->getMessage());
        }
    }

    private function normalizeDoc(array $doc): array
    {
        // convert Mongo fields to simple PHP array
        if (isset($doc['_id'])) $doc['id'] = (string)$doc['_id'];
        if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
            $dt = $doc['date']->toDateTime();
            $doc['date'] = $dt->format('Y-m-d H:i:s');
        }
        return $doc;
    }
}
