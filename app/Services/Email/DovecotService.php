<?php

namespace App\Services\Email;

use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\EmailAutoresponder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DovecotService implements EmailInterface
{
    protected string $mailDir;
    protected string $configDir;
    protected string $passwdFile;
    protected string $aliasesFile;

    public function __construct()
    {
        $this->mailDir = config('freepanel.mail_dir', '/var/mail/vhosts');
        $this->configDir = config('freepanel.dovecot_config_dir', '/etc/dovecot');
        $this->passwdFile = "{$this->configDir}/users";
        $this->aliasesFile = '/etc/aliases';
    }

    public function createMailbox(EmailAccount $account, string $password): void
    {
        $domain = $account->domain;
        $email = "{$account->username}@{$domain->name}";
        $mailbox = "{$this->mailDir}/{$domain->name}/{$account->username}";

        // Create mailbox directory structure
        $this->createMailboxDirectories($mailbox);

        // Hash password using dovecot's doveadm
        $hashedPassword = $this->hashPassword($password);

        // Add to passwd file
        $this->addToPasswdFile($email, $hashedPassword, $mailbox, $account->quota);

        // Set ownership (vmail user typically uid 5000)
        $this->setMailOwnership($mailbox);
    }

    public function deleteMailbox(EmailAccount $account): void
    {
        $domain = $account->domain;
        $email = "{$account->username}@{$domain->name}";
        $mailbox = "{$this->mailDir}/{$domain->name}/{$account->username}";

        // Remove from passwd file
        $this->removeFromPasswdFile($email);

        // Delete mailbox directory
        if (File::isDirectory($mailbox)) {
            File::deleteDirectory($mailbox);
        }
    }

    public function updatePassword(EmailAccount $account, string $password): void
    {
        $email = "{$account->username}@{$account->domain->name}";
        $hashedPassword = $this->hashPassword($password);

        $this->updatePasswdEntry($email, 'password', $hashedPassword);
    }

    public function updateQuota(EmailAccount $account, int $quotaMb): void
    {
        $email = "{$account->username}@{$account->domain->name}";
        $quota = $quotaMb > 0 ? "{$quotaMb}M" : '';

        $this->updatePasswdEntry($email, 'quota', $quota);
    }

    public function getQuotaUsage(EmailAccount $account): int
    {
        $mailbox = "{$this->mailDir}/{$account->domain->name}/{$account->username}";

        if (!File::isDirectory($mailbox)) {
            return 0;
        }

        // Calculate directory size
        $result = Process::run("du -sb {$mailbox} 2>/dev/null | cut -f1");
        return (int) trim($result->output());
    }

    public function createForwarder(EmailForwarder $forwarder): void
    {
        $source = "{$forwarder->source}@{$forwarder->domain->name}";

        // Add to virtual aliases
        $virtualAliases = "{$this->configDir}/virtual_aliases";
        $entry = "{$source} {$forwarder->destination}\n";

        File::append($virtualAliases, $entry);

        // Rebuild aliases database
        Process::run('postmap ' . $virtualAliases);
    }

    public function deleteForwarder(EmailForwarder $forwarder): void
    {
        $source = "{$forwarder->source}@{$forwarder->domain->name}";
        $virtualAliases = "{$this->configDir}/virtual_aliases";

        if (File::exists($virtualAliases)) {
            $content = File::get($virtualAliases);
            $lines = explode("\n", $content);

            $newLines = array_filter($lines, function ($line) use ($source) {
                return !str_starts_with(trim($line), $source);
            });

            File::put($virtualAliases, implode("\n", $newLines));
            Process::run('postmap ' . $virtualAliases);
        }
    }

    public function createAutoresponder(EmailAutoresponder $autoresponder): void
    {
        $email = $autoresponder->emailAccount;
        $address = "{$email->username}@{$email->domain->name}";

        // Create sieve script for autoresponder
        $sieveDir = "{$this->mailDir}/{$email->domain->name}/{$email->username}/sieve";
        if (!File::isDirectory($sieveDir)) {
            File::makeDirectory($sieveDir, 0755, true);
        }

        $sieveScript = $this->generateAutoresponderSieve($autoresponder);
        File::put("{$sieveDir}/autoresponder.sieve", $sieveScript);

        // Compile sieve script
        Process::run("sievec {$sieveDir}/autoresponder.sieve");

        // Set ownership
        $this->setMailOwnership($sieveDir);
    }

    public function updateAutoresponder(EmailAutoresponder $autoresponder): void
    {
        // Just recreate the autoresponder
        $this->createAutoresponder($autoresponder);
    }

    public function deleteAutoresponder(EmailAutoresponder $autoresponder): void
    {
        $email = $autoresponder->emailAccount;
        $sieveDir = "{$this->mailDir}/{$email->domain->name}/{$email->username}/sieve";

        $sieveFile = "{$sieveDir}/autoresponder.sieve";
        $compiledFile = "{$sieveDir}/autoresponder.svbin";

        if (File::exists($sieveFile)) {
            File::delete($sieveFile);
        }
        if (File::exists($compiledFile)) {
            File::delete($compiledFile);
        }
    }

    public function mailboxExists(string $email): bool
    {
        if (!File::exists($this->passwdFile)) {
            return false;
        }

        $content = File::get($this->passwdFile);
        return str_contains($content, "{$email}:");
    }

    protected function createMailboxDirectories(string $mailbox): void
    {
        $dirs = [
            $mailbox,
            "{$mailbox}/cur",
            "{$mailbox}/new",
            "{$mailbox}/tmp",
            "{$mailbox}/.Drafts/cur",
            "{$mailbox}/.Drafts/new",
            "{$mailbox}/.Drafts/tmp",
            "{$mailbox}/.Sent/cur",
            "{$mailbox}/.Sent/new",
            "{$mailbox}/.Sent/tmp",
            "{$mailbox}/.Trash/cur",
            "{$mailbox}/.Trash/new",
            "{$mailbox}/.Trash/tmp",
            "{$mailbox}/.Spam/cur",
            "{$mailbox}/.Spam/new",
            "{$mailbox}/.Spam/tmp",
        ];

        foreach ($dirs as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0700, true);
            }
        }
    }

    protected function hashPassword(string $password): string
    {
        $result = Process::run("doveadm pw -s SHA512-CRYPT -p " . escapeshellarg($password));
        return trim($result->output());
    }

    protected function addToPasswdFile(string $email, string $hashedPassword, string $mailbox, int $quotaMb): void
    {
        // Format: user:password:uid:gid:gecos:home:shell:extra_fields
        // For Dovecot: email:{password}:5000:5000::{mailbox}::userdb_quota_rule=*:storage={quota}M

        $quota = $quotaMb > 0 ? "userdb_quota_rule=*:storage={$quotaMb}M" : '';
        $entry = "{$email}:{$hashedPassword}:5000:5000::{$mailbox}::{$quota}\n";

        File::append($this->passwdFile, $entry);
    }

    protected function removeFromPasswdFile(string $email): void
    {
        if (!File::exists($this->passwdFile)) {
            return;
        }

        $content = File::get($this->passwdFile);
        $lines = explode("\n", $content);

        $newLines = array_filter($lines, function ($line) use ($email) {
            return !str_starts_with($line, "{$email}:");
        });

        File::put($this->passwdFile, implode("\n", $newLines));
    }

    protected function updatePasswdEntry(string $email, string $field, string $value): void
    {
        if (!File::exists($this->passwdFile)) {
            return;
        }

        $content = File::get($this->passwdFile);
        $lines = explode("\n", $content);

        foreach ($lines as $i => $line) {
            if (str_starts_with($line, "{$email}:")) {
                $parts = explode(':', $line);

                switch ($field) {
                    case 'password':
                        $parts[1] = $value;
                        break;
                    case 'quota':
                        $parts[7] = $value ? "userdb_quota_rule=*:storage={$value}" : '';
                        break;
                }

                $lines[$i] = implode(':', $parts);
                break;
            }
        }

        File::put($this->passwdFile, implode("\n", $lines));
    }

    protected function generateAutoresponderSieve(EmailAutoresponder $autoresponder): string
    {
        $conditions = [];

        if ($autoresponder->start_time) {
            $conditions[] = 'currentdate :value "ge" "date" "' . $autoresponder->start_time->format('Y-m-d') . '"';
        }

        if ($autoresponder->end_time) {
            $conditions[] = 'currentdate :value "le" "date" "' . $autoresponder->end_time->format('Y-m-d') . '"';
        }

        $conditionStr = !empty($conditions) ? 'if allof(' . implode(', ', $conditions) . ') {' : '';
        $endIf = !empty($conditions) ? '}' : '';

        $subject = addslashes($autoresponder->subject);
        $body = addslashes($autoresponder->body);

        return <<<SIEVE
require ["vacation", "date", "relational"];

{$conditionStr}
vacation :days 1 :subject "{$subject}" "{$body}";
{$endIf}
SIEVE;
    }

    protected function setMailOwnership(string $path): void
    {
        // vmail user/group typically 5000:5000
        Process::run("chown -R 5000:5000 " . escapeshellarg($path));
    }
}
