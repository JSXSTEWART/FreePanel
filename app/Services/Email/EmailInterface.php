<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\EmailAutoresponder;

interface EmailInterface
{
    /**
     * Create a mailbox for an email account
     */
    public function createMailbox(EmailAccount $account, string $password): void;

    /**
     * Delete a mailbox
     */
    public function deleteMailbox(EmailAccount $account): void;

    /**
     * Update mailbox password
     */
    public function updatePassword(EmailAccount $account, string $password): void;

    /**
     * Update mailbox quota
     */
    public function updateQuota(EmailAccount $account, int $quotaMb): void;

    /**
     * Get mailbox quota usage
     */
    public function getQuotaUsage(EmailAccount $account): int;

    /**
     * Create an email forwarder
     */
    public function createForwarder(EmailForwarder $forwarder): void;

    /**
     * Delete an email forwarder
     */
    public function deleteForwarder(EmailForwarder $forwarder): void;

    /**
     * Create an autoresponder
     */
    public function createAutoresponder(EmailAutoresponder $autoresponder): void;

    /**
     * Update an autoresponder
     */
    public function updateAutoresponder(EmailAutoresponder $autoresponder): void;

    /**
     * Delete an autoresponder
     */
    public function deleteAutoresponder(EmailAutoresponder $autoresponder): void;

    /**
     * Check if mailbox exists
     */
    public function mailboxExists(string $email): bool;
}
