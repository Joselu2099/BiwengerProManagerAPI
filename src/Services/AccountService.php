<?php

namespace BiwengerProManagerAPI\Services;

use BiwengerProManagerAPI\Models\Account;
use BiwengerProManagerAPI\Database\AccountsRepository;
use BiwengerProManagerAPI\Utils\Logger;

class AccountService
{
    private $repository;

    public function __construct(AccountsRepository $repository = null)
    {
        $this->repository = $repository;
    }

    /**
     * Process and store account data, return Account model
     */
    public function processAccountData(array $accountData): Account
    {
        // Store/update account in database if repository is available
        if ($this->repository) {
            try {
                $this->repository->insertOrUpdateAccount($accountData);
                Logger::info('AccountService: persisted account ' . ($accountData['id'] ?? $accountData['email'] ?? 'unknown'));
            } catch (\Throwable $e) {
                Logger::error('AccountService: failed to persist account: ' . $e->getMessage());
            }
        } else {
            Logger::info('AccountService: no repository configured, skipping persistence');
        }

        // Return Account model
        return new Account($accountData);
    }

    /**
     * Get account by ID from database
     */
    public function getAccountById(int $id): ?Account
    {
        if (!$this->repository) {
            Logger::info('AccountService: getAccountById called but no repository configured');
            return null;
        }

        $accountData = $this->repository->getAccountById($id);
        if (!$accountData) {
            Logger::info('AccountService: account not found id=' . $id);
            return null;
        }

        return new Account($accountData);
    }

    /**
     * Get account by email from database
     */
    public function getAccountByEmail(string $email): ?Account
    {
        if (!$this->repository) {
            Logger::info('AccountService: getAccountByEmail called but no repository configured');
            return null;
        }

        $accountData = $this->repository->getAccountByEmail($email);
        if (!$accountData) {
            Logger::info('AccountService: account not found email=' . $email);
            return null;
        }

        return new Account($accountData);
    }

    /**
     * Find an Account model by a session/token string (if repository stores tokens).
     */
    public function getAccountByToken(string $token): ?Account
    {
        if (!$this->repository) {
            Logger::info('AccountService: getAccountByToken called but no repository configured');
            return null;
        }

        $accountData = $this->repository->getAccountByToken($token);
        if (!$accountData) return null;

        return new Account($accountData);
    }
}
