<?php

namespace App\Services\Ftp;

use App\Models\Account;
use App\Models\FtpAccount;

interface FtpInterface
{
    /**
     * Create an FTP account
     */
    public function createAccount(FtpAccount $account, string $password): void;

    /**
     * Update an FTP account
     */
    public function updateAccount(FtpAccount $account): void;

    /**
     * Delete an FTP account
     */
    public function deleteAccount(FtpAccount $account): void;

    /**
     * Update FTP account password
     */
    public function updatePassword(FtpAccount $account, string $password): void;

    /**
     * Get active FTP sessions for an account
     */
    public function getActiveSessions(Account $account): array;

    /**
     * Kill an FTP session
     */
    public function killSession(string $sessionId, Account $account): void;
}
