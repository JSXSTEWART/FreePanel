<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\EmailAutoresponder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class EximService implements EmailInterface
{
    protected string $mailDir = '/var/mail/vhosts';
    protected string $virtualDomainsFile = '/etc/exim4/virtual_domains';
    protected string $virtualUsersFile = '/etc/exim4/virtual_users';
    protected string $virtualAliasesFile = '/etc/exim4/virtual_aliases';

    public function createMailbox(EmailAccount $account, string $password): void
    {
        $domain = $account->domain->name;
        $localPart = explode('@', $account->email)[0];
        $mailboxPath = "{$this->mailDir}/{$domain}/{$localPart}";

        // Create mailbox directory structure
        if (!is_dir($mailboxPath)) {
            mkdir($mailboxPath, 0700, true);
            mkdir("{$mailboxPath}/cur", 0700);
            mkdir("{$mailboxPath}/new", 0700);
            mkdir("{$mailboxPath}/tmp", 0700);
        }

        // Add to virtual users file
        $hashedPassword = $this->hashPassword($password);
        $entry = "{$account->email}:{$hashedPassword}";

        $this->appendToFile($this->virtualUsersFile, $entry);

        // Ensure domain is in virtual domains
        $this->addVirtualDomain($domain);

        // Set ownership
        Process::run("chown -R mail:mail {$mailboxPath}");

        Log::info("Exim mailbox created for {$account->email}");
    }

    public function deleteMailbox(EmailAccount $account): void
    {
        $domain = $account->domain->name;
        $localPart = explode('@', $account->email)[0];
        $mailboxPath = "{$this->mailDir}/{$domain}/{$localPart}";

        // Remove mailbox directory
        if (is_dir($mailboxPath)) {
            Process::run("rm -rf {$mailboxPath}");
        }

        // Remove from virtual users file
        $this->removeFromFile($this->virtualUsersFile, $account->email);

        Log::info("Exim mailbox deleted for {$account->email}");
    }

    public function updatePassword(EmailAccount $account, string $password): void
    {
        $hashedPassword = $this->hashPassword($password);

        // Read file, update the line, write back
        $lines = file($this->virtualUsersFile, FILE_IGNORE_NEW_LINES);
        $updated = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, "{$account->email}:")) {
                $updated[] = "{$account->email}:{$hashedPassword}";
            } else {
                $updated[] = $line;
            }
        }

        file_put_contents($this->virtualUsersFile, implode("\n", $updated) . "\n");

        Log::info("Password updated for {$account->email}");
    }

    public function updateQuota(EmailAccount $account, int $quotaMb): void
    {
        // Exim doesn't handle quotas directly - this would be handled by the MDA (Dovecot)
        // Just log the request
        Log::info("Quota update requested for {$account->email}: {$quotaMb}MB");
    }

    public function getQuotaUsage(EmailAccount $account): int
    {
        $domain = $account->domain->name;
        $localPart = explode('@', $account->email)[0];
        $mailboxPath = "{$this->mailDir}/{$domain}/{$localPart}";

        if (!is_dir($mailboxPath)) {
            return 0;
        }

        $result = Process::run("du -sm {$mailboxPath} | cut -f1");
        return (int) trim($result->output());
    }

    public function createForwarder(EmailForwarder $forwarder): void
    {
        $source = $forwarder->source . '@' . $forwarder->domain->name;
        $entry = "{$source}: {$forwarder->destination}";

        $this->appendToFile($this->virtualAliasesFile, $entry);
        $this->reload();

        Log::info("Exim forwarder created: {$source} -> {$forwarder->destination}");
    }

    public function deleteForwarder(EmailForwarder $forwarder): void
    {
        $source = $forwarder->source . '@' . $forwarder->domain->name;

        $this->removeFromFile($this->virtualAliasesFile, $source);
        $this->reload();

        Log::info("Exim forwarder deleted: {$source}");
    }

    public function createAutoresponder(EmailAutoresponder $autoresponder): void
    {
        // TODO: Implement Exim autoresponder using one of these approaches:
        //
        // Option 1: Use Exim's autoreply transport (recommended for Exim-only setups)
        // - Create a filter file at: /var/mail/vhosts/{domain}/{user}/.autoresponder
        // - Add router in Exim config that checks for .autoresponder file
        // - Filter format example:
        //   if $header_from: does not contain $local_part@$domain
        //   then
        //     mail to: $header_from:
        //     subject: "Auto: $header_subject:"
        //     text: "{message}"
        //   endif
        //
        // Option 2: Use vacation program (/usr/bin/vacation)
        // - Create .vacation.msg file with the autoresponder message
        // - Create .vacation.db for tracking sent responses (avoid reply loops)
        // - Configure Exim to pipe to vacation program
        //
        // Option 3: Delegate to Dovecot Sieve (if using Dovecot for LDA)
        // - Create Sieve script at: /var/mail/vhosts/{domain}/{user}/sieve/autoresponder.sieve
        // - See DovecotService for Sieve implementation example
        //
        // Implementation checklist:
        // 1. Check date range (start_date, end_date) if autoresponder should be time-limited
        // 2. Store list of already-responded addresses to prevent reply loops
        // 3. Only reply once per sender per day/period
        // 4. Exclude mailing lists, bounce messages, and spam
        // 5. Set proper headers (Auto-Submitted: auto-replied, X-Auto-Response-Suppress)

        $this->writeAutoresponderConfig($autoresponder);
        Log::info("Autoresponder created for {$autoresponder->emailAccount->email}");
    }

    public function updateAutoresponder(EmailAutoresponder $autoresponder): void
    {
        // TODO: Update the autoresponder configuration
        // - Regenerate the filter/script file with new message/settings
        // - Clear the response tracking database if desired

        $this->writeAutoresponderConfig($autoresponder);
        Log::info("Autoresponder updated for {$autoresponder->emailAccount->email}");
    }

    public function deleteAutoresponder(EmailAutoresponder $autoresponder): void
    {
        // TODO: Remove autoresponder configuration
        // - Delete .autoresponder filter file
        // - Delete .vacation.db tracking file
        // - Remove any Sieve scripts if using Dovecot

        $email = $autoresponder->emailAccount->email;
        $domain = $autoresponder->emailAccount->domain->name;
        $localPart = explode('@', $email)[0];
        $autoresponderPath = "{$this->mailDir}/{$domain}/{$localPart}/.autoresponder";

        if (file_exists($autoresponderPath)) {
            unlink($autoresponderPath);
        }

        // Also remove vacation db if exists
        $vacationDb = "{$this->mailDir}/{$domain}/{$localPart}/.vacation.db";
        if (file_exists($vacationDb)) {
            unlink($vacationDb);
        }

        Log::info("Autoresponder deleted for {$email}");
    }

    /**
     * Write autoresponder configuration file
     *
     * TODO: Implement based on chosen autoresponder approach above
     */
    protected function writeAutoresponderConfig(EmailAutoresponder $autoresponder): void
    {
        $email = $autoresponder->emailAccount->email;
        $domain = $autoresponder->emailAccount->domain->name;
        $localPart = explode('@', $email)[0];
        $mailboxPath = "{$this->mailDir}/{$domain}/{$localPart}";

        // Ensure mailbox directory exists
        if (!is_dir($mailboxPath)) {
            return;
        }

        // TODO: Generate appropriate autoresponder format based on configuration
        // For now, create a simple vacation-style message file
        $autoresponderPath = "{$mailboxPath}/.autoresponder";

        $message = sprintf(
            "From: %s\n" .
            "Subject: %s\n" .
            "Content-Type: text/plain; charset=utf-8\n" .
            "Auto-Submitted: auto-replied\n" .
            "X-Auto-Response-Suppress: All\n" .
            "\n" .
            "%s",
            $email,
            $autoresponder->subject ?? 'Auto-Reply',
            $autoresponder->message ?? 'I am currently unavailable.'
        );

        file_put_contents($autoresponderPath, $message);
        chmod($autoresponderPath, 0600);
        Process::run("chown mail:mail " . escapeshellarg($autoresponderPath));
    }

    public function mailboxExists(string $email): bool
    {
        if (!file_exists($this->virtualUsersFile)) {
            return false;
        }

        $content = file_get_contents($this->virtualUsersFile);
        return str_contains($content, "{$email}:");
    }

    protected function hashPassword(string $password): string
    {
        // Use SHA512-CRYPT for Exim
        return '{SHA512-CRYPT}' . crypt($password, '$6$' . bin2hex(random_bytes(8)) . '$');
    }

    protected function addVirtualDomain(string $domain): void
    {
        if (!file_exists($this->virtualDomainsFile)) {
            file_put_contents($this->virtualDomainsFile, '');
        }

        $content = file_get_contents($this->virtualDomainsFile);
        if (!str_contains($content, $domain)) {
            $this->appendToFile($this->virtualDomainsFile, $domain);
        }
    }

    protected function appendToFile(string $file, string $line): void
    {
        file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    protected function removeFromFile(string $file, string $search): void
    {
        if (!file_exists($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $filtered = array_filter($lines, fn($line) => !str_starts_with($line, "{$search}:") && $line !== $search);

        file_put_contents($file, implode("\n", $filtered) . "\n");
    }

    protected function reload(): void
    {
        Process::run('systemctl reload exim4');
    }
}
