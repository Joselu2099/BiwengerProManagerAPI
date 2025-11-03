<?php

namespace BiwengerProManagerAPI\Database;

use MongoDB\BSON\UTCDateTime;
use BiwengerProManagerAPI\Utils\Logger;

class AccountsRepository
{
    private $db;
    private $collectionName = 'accounts';

    public function __construct($db = null)
    {
        // Accept a MongoDB\Database or create one via MongoConnection
        $this->db = $db ?? MongoConnection::getInstance()->getDb();
        // create indexes if necessary
        $this->db->selectCollection($this->collectionName)->createIndex(['_id' => 1]);
        $this->db->selectCollection($this->collectionName)->createIndex(['email' => 1]);
        // index token for fast lookup by session/token
        $this->db->selectCollection($this->collectionName)->createIndex(['token' => 1]);
    }

    public function existAccount($id): bool
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne(['_id' => (int)$id]);
            return $doc !== null;
        } catch (\Throwable $e) {
            Logger::error('AccountsRepository::existAccount failed: ' . $e->getMessage());
            return false;
        }
    }

    public function insertOrUpdateAccount(array $accountData): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);

            $id = (int)$accountData['id'];
            $doc = [
                '_id' => $id,
                'token' => $accountData['token'] ?? null,
                'name' => $accountData['name'] ?? '',
                'email' => $accountData['email'] ?? '',
                'phone' => $accountData['phone'] ?? null,
                'locale' => $accountData['locale'] ?? 'es',
                'birthday' => $accountData['birthday'] ?? null,
                'status' => $accountData['status'] ?? 'unknown',
                'credits' => (int)($accountData['credits'] ?? 0),
                'created' => $accountData['created'] ?? null,
                'newsletter' => (bool)($accountData['newsletter'] ?? false),
                'unreadMessages' => (bool)($accountData['unreadMessages'] ?? false),
                'lastAccess' => $accountData['lastAccess'] ?? null,
                'source' => $accountData['source'] ?? null,
                'devices' => $accountData['devices'] ?? [],
                'updated_at' => new \MongoDB\BSON\UTCDateTime(), // Track when we last updated this record
            ];

            $res = $col->replaceOne(['_id' => $id], $doc, ['upsert' => true]);
            // Log operation summary
            $msg = sprintf('AccountsRepository: upsert id=%s modified=%d upsertedId=%s', $id, $res->getModifiedCount(), json_encode($res->getUpsertedId()));
            Logger::info($msg);
        } catch (\Throwable $e) {
            Logger::error('AccountsRepository::insertOrUpdateAccount failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAccountById($id): ?array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $doc = $col->findOne(['_id' => (int)$id]);
            if (!$doc) return null;

            $arr = (array)$doc;
            // Convert _id to id for compatibility
            if (isset($arr['_id'])) {
                $arr['id'] = $arr['_id'];
                unset($arr['_id']);
            }
            // Remove our internal tracking field from the response
            unset($arr['updated_at']);

            return $arr;
        } catch (\Throwable $e) {
            Logger::error('AccountsRepository::getAccountById failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getAccountByEmail(string $email): ?array
    {
        $col = $this->db->selectCollection($this->collectionName);
        $doc = $col->findOne(['email' => $email]);
        if (!$doc) return null;

        $arr = (array)$doc;
        // Convert _id to id for compatibility
        if (isset($arr['_id'])) {
            $arr['id'] = $arr['_id'];
            unset($arr['_id']);
        }
        // Remove our internal tracking field from the response
        unset($arr['updated_at']);

        return $arr;
    }

    /**
     * Find account by session token (if stored) and return array representation
     */
    public function getAccountByToken(string $token): ?array
    {
        $col = $this->db->selectCollection($this->collectionName);
        $doc = $col->findOne(['token' => $token]);
        if (!$doc) return null;

        $arr = (array)$doc;
        // Convert _id to id for compatibility
        if (isset($arr['_id'])) {
            $arr['id'] = $arr['_id'];
            unset($arr['_id']);
        }
        // Remove our internal tracking field from the response
        unset($arr['updated_at']);

        return $arr;
    }

    public function getAllAccounts(): array
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $cursor = $col->find();
            $out = [];

            foreach ($cursor as $doc) {
                $arr = (array)$doc;
                // Convert _id to id for compatibility
                if (isset($arr['_id'])) {
                    $arr['id'] = $arr['_id'];
                    unset($arr['_id']);
                }
                // Remove our internal tracking field from the response
                unset($arr['updated_at']);
                $out[] = $arr;
            }

            Logger::info('AccountsRepository: getAllAccounts count=' . count($out));
            return $out;
        } catch (\Throwable $e) {
            Logger::error('AccountsRepository::getAllAccounts failed: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteAccount($id): void
    {
        try {
            $col = $this->db->selectCollection($this->collectionName);
            $res = $col->deleteOne(['_id' => (int)$id]);
            Logger::info('AccountsRepository: deleteAccount id=' . (int)$id . ' deleted=' . $res->getDeletedCount());
        } catch (\Throwable $e) {
            Logger::error('AccountsRepository::deleteAccount failed: ' . $e->getMessage());
        }
    }
}
