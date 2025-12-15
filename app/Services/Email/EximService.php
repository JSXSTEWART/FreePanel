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
        // Autoresponders are typically handled by Dovecot Sieve or a separate system
        Log::info("Autoresponder created for {$autoresponder->emailAccount->email}");
    }

    public function updateAutoresponder(EmailAutoresponder $autoresponder): void
    {
        Log::info("Autoresponder updated for {$autoresponder->emailAccount->email}");
    }

    public function deleteAutoresponder(EmailAutoresponder $autoresponder): void
    {
        Log::info("Autoresponder deleted for {$autoresponder->emailAccount->email}");
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
