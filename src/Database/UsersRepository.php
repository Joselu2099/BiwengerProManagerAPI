<?php
namespace BiwengerProManagerAPI\Database;

use BiwengerProManagerAPI\Models\User;
use BiwengerProManagerAPI\Models\Account;
use BiwengerProManagerAPI\Database\AccountsRepository;
use MongoDB\Model\BSONDocument;
use BiwengerProManagerAPI\Utils\Utils;
use BiwengerProManagerAPI\Utils\Logger;

class UsersRepository
{
    private $collection;

    public function __construct()
    {
        $mongoConnection = MongoConnection::getInstance();
        $this->collection = $mongoConnection->getDb()->selectCollection('users');
        
    // Crear índice en league_id para optimizar consultas
    $this->collection->createIndex(['league_id' => 1]);
    // Índice en account_id para acelerar búsquedas por cuenta asociada
    $this->collection->createIndex(['account_id' => 1]);
    // NOTE: Historically we used a composite index on league_id+user_id, but
    // the repository now stores the canonical user id in `_id`. Avoid relying
    // on a separate `user_id` field to prevent duplication.
    }

    /**
     * Recursively normalize BSON types to PHP arrays/scalars.
     * - BSONDocument / BSONArray / Traversable -> associative array
     * - UTCDateTime -> ISO8601 string
     * - ObjectId and other objects with __toString -> string
     */
    private function normalizeRecursive($val)
    {
        // Traversable (BSONDocument, BSONArray)
        if ($val instanceof \Traversable) {
            $out = [];
            foreach ($val as $k => $v) {
                $out[$k] = $this->normalizeRecursive($v);
            }
            return $out;
        }

        if (is_array($val)) {
            $out = [];
            foreach ($val as $k => $v) $out[$k] = $this->normalizeRecursive($v);
            return $out;
        }

        if (is_object($val)) {
            // MongoDB UTCDateTime -> DateTime
            if (method_exists($val, 'toDateTime')) {
                try {
                    $dt = $val->toDateTime();
                    if ($dt instanceof \DateTimeInterface) return $dt->format(\DateTime::ATOM);
                } catch (\Throwable $e) {
                    // fallback to string cast
                }
            }
            if (method_exists($val, '__toString')) {
                try { return (string)$val; } catch (\Throwable $e) { /* fallthrough */ }
            }
            // Unknown object: return as-is (caller should handle)
            return $val;
        }

        // scalar
        return $val;
    }

    /**
     * Insertar un nuevo usuario en la base de datos
     */
    public function insertUser(User $user, string $leagueId): bool
    {
        try {
            // Build a full document and use replaceOne with upsert so _id is always the PK
            $userData = (array) $user->jsonSerialize();
            // Avoid duplicating id fields in the stored document: keep canonical _id only
            if (array_key_exists('id', $userData)) unset($userData['id']);
            if (array_key_exists('user_id', $userData)) unset($userData['user_id']);
            $userId = $user->getId();
            $id = Utils::getAsInt($userId);

            $userData['_id'] = $id;
            $userData['league_id'] = Utils::getAsInt($leagueId);
            $userData['created_at'] = new \MongoDB\BSON\UTCDateTime();
            $userData['updated_at'] = new \MongoDB\BSON\UTCDateTime();

            // Use replaceOne with upsert to ensure the document is stored under _id
            $res = $this->collection->replaceOne(['_id' => $id], $userData, ['upsert' => true]);
            Logger::info('UsersRepository: insertUser id=' . $id . ' league=' . $leagueId);
            return true;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::insertUser failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener un usuario específico por ID de usuario y liga
     */
    public function getUser(string $userId, string $leagueId): ?User
    {
        try {
            // Prefer finding by _id (the canonical primary key). Fallback to user_id for compatibility.
            $maybeId = Utils::getAsInt($userId);
            $maybeLeague = Utils::getAsInt($leagueId);

            // Prefer finding by _id (the canonical primary key).
            $document = $this->collection->findOne(['_id' => $maybeId, 'league_id' => $maybeLeague]);
            // legacy fallback intentionally removed: repository stores user id in _id only
            if (!$document) {
                return null;
            }

            // Convertir BSONDocument a array si es necesario
            if ($document instanceof BSONDocument) {
                $userData = iterator_to_array($document);
            } else {
                $userData = (array) $document;
            }

            return $this->mapToUser($userData);
        } catch (\Exception $e) {
            Logger::error('UsersRepository::getUser failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todos los usuarios de una liga
     */
    public function getUsersByLeague(string $leagueId): array
    {
        try {
            $filter = ['league_id' => $leagueId];
            $options = ['sort' => ['position' => 1]]; // Ordenar por posición

            $cursor = $this->collection->find($filter, $options);
            $users = [];

            foreach ($cursor as $document) {
                // Convertir BSONDocument a array si es necesario
                if ($document instanceof BSONDocument) {
                    $userData = iterator_to_array($document);
                } else {
                    $userData = (array) $document;
                }

                $user = $this->mapToUser($userData);
                if ($user) {
                    $users[] = $user;
                }
            }

            Logger::info('UsersRepository: getUsersByLeague league=' . $leagueId . ' count=' . count($users));
            return $users;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::getUsersByLeague failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar un usuario existente
     */
    public function updateUser(User $user, string $leagueId): bool
    {
        try {
            // Update by _id primarily (and keep user_id for compatibility)
            $userId = $user->getId();
            $id = Utils::getAsInt($userId);
            $league = Utils::getAsInt($leagueId);

            $filter = ['_id' => $id, 'league_id' => $league];

            $userData = (array) $user->jsonSerialize();
            // Avoid duplicating id fields in the stored document: keep canonical _id only
            if (array_key_exists('id', $userData)) unset($userData['id']);
            if (array_key_exists('user_id', $userData)) unset($userData['user_id']);
            $userData['league_id'] = $league;
            $userData['_id'] = $id;
            $userData['updated_at'] = new \MongoDB\BSON\UTCDateTime();

            $updateData = ['$set' => $userData];
            $result = $this->collection->updateOne($filter, $updateData);

            $ok = $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
            if ($ok) Logger::info('UsersRepository: updateUser id=' . $id . ' league=' . $league);
            return $ok;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::updateUser failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar o actualizar un usuario (upsert)
     */
    public function saveUser(User $user, string $leagueId): bool
    {
        try {
            // Save entire user document by _id using replaceOne upsert to keep _id as primary key
            $userId = $user->getId();
            $id = Utils::getAsInt($userId);
            $league = Utils::getAsInt($leagueId);

            $userData = (array) $user->jsonSerialize();
            // Avoid duplicating id fields in the stored document: keep canonical _id only
            if (array_key_exists('id', $userData)) unset($userData['id']);
            if (array_key_exists('user_id', $userData)) unset($userData['user_id']);
            $userData['league_id'] = $league;
            $userData['_id'] = $id;
            $userData['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            if (!isset($userData['created_at'])) {
                $userData['created_at'] = new \MongoDB\BSON\UTCDateTime();
            }

            $res = $this->collection->replaceOne(['_id' => $id], $userData, ['upsert' => true]);
            Logger::info('UsersRepository: saveUser id=' . $id . ' league=' . $league);
            return true;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::saveUser failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un usuario
     */
    public function deleteUser(string $userId, string $leagueId): bool
    {
        try {
            $id = Utils::getAsInt($userId);
            $league = Utils::getAsInt($leagueId);

            // Prefer deleting by canonical _id; fallback to legacy user_id if present in DB
            $result = $this->collection->deleteOne(['_id' => $id, 'league_id' => $league]);
            if ($result->getDeletedCount() > 0) {
                Logger::info('UsersRepository: deleteUser id=' . $id . ' league=' . $league . ' deleted=1');
                return true;
            }

            // Fallback for legacy documents that still use user_id
            $result = $this->collection->deleteOne(['user_id' => $userId, 'league_id' => $leagueId]);
            $deleted = $result->getDeletedCount();
            Logger::info('UsersRepository: deleteUser id=' . $userId . ' league=' . $leagueId . ' deleted=' . $deleted);
            return $deleted > 0;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::deleteUser failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar todos los usuarios de una liga
     */
    public function deleteUsersByLeague(string $leagueId): int
    {
        try {
            $filter = ['league_id' => $leagueId];
            $result = $this->collection->deleteMany($filter);
            $deleted = $result->getDeletedCount();
            Logger::info('UsersRepository: deleteUsersByLeague league=' . $leagueId . ' deleted=' . $deleted);
            return $deleted;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::deleteUsersByLeague failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sincronizar usuarios de una liga con datos frescos de la API
     */
    public function syncUsersFromApi(array $standingsData, string $leagueId): bool
    {
        try {
            // Eliminar usuarios existentes de la liga
            $deleted = $this->deleteUsersByLeague($leagueId);
            Logger::info('UsersRepository: syncUsersFromApi league=' . $leagueId . ' previous_deleted=' . $deleted . ' incoming=' . count($standingsData));

            // Insertar usuarios actualizados
            $inserted = 0;
            foreach ($standingsData as $standing) {
                $user = $this->mapStandingToUser($standing);
                if ($user) {
                    $ok = $this->insertUser($user, $leagueId);
                    if ($ok) $inserted++;
                }
            }

            Logger::info('UsersRepository: syncUsersFromApi completed league=' . $leagueId . ' inserted=' . $inserted);
            return true;
        } catch (\Exception $e) {
            Logger::error('UsersRepository::syncUsersFromApi failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mapear datos de standing de la API a modelo User
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
            Logger::error('UsersRepository::mapStandingToUser failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mapear documento de MongoDB a modelo User
     */
    private function mapToUser(array $userData): ?User
    {
        try {
            // Prefer _id if present (may be BSON ObjectId or a scalar id set by our code)
            $id = null;
            if (array_key_exists('_id', $userData)) {
                $raw = $userData['_id'];
                if (is_object($raw) && method_exists($raw, '__toString')) {
                    $id = (string)$raw;
                } else {
                    $id = $raw;
                }
            } elseif (array_key_exists('id', $userData)) {
                $id = $userData['id'];
            }

            // Normalize BSON types in the document so nested BSONArray/BSONDocument values
            // (like lastPositions) become PHP arrays/scalars compatible with User constructor
            $userData = $this->normalizeRecursive($userData);

            // Build Account object if account data is present. If we only have an
            // account_id, try to load the full account from AccountsRepository so
            // callers receive a populated Account object (including token if present).
            $account = null;
            if (array_key_exists('account', $userData) && is_array($userData['account'])) {
                $account = new Account($userData['account']);
            } elseif (array_key_exists('account_id', $userData) && $userData['account_id'] !== null) {
                try {
                    $accountsRepo = new AccountsRepository();
                    $acc = $accountsRepo->getAccountById((int)$userData['account_id']);
                    if (is_array($acc) && !empty($acc)) {
                        $account = new Account($acc);
                    } else {
                        // fallback to minimal Account with id only
                        $account = new Account(['id' => $userData['account_id']]);
                    }
                } catch (\Throwable $e) {
                    // If repository lookup fails, provide minimal Account object
                    Logger::error('UsersRepository::mapToUser accountsRepo failed: ' . $e->getMessage());
                    $account = new Account(['id' => $userData['account_id']]);
                }
            }

            return new User(
                $id,
                $userData['name'] ?? '',
                $userData['icon'] ?? '',
                $userData['points'] ?? 0,
                $userData['lastPositions'] ?? [],
                $userData['position'] ?? 0,
                $userData['positionInc'] ?? null,
                $userData['role'] ?? '',
                $account
            );
        } catch (\Exception $e) {
            Logger::error('UsersRepository::mapToUser failed: ' . $e->getMessage());
            return null;
        }
    }
}