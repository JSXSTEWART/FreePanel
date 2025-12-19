<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitRepository extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'path',
        'clone_url',
        'branch',
        'deploy_path',
        'auto_deploy',
        'deploy_script',
        'last_commit_hash',
        'last_commit_message',
        'last_push_at',
        'is_private',
    ];

    protected $casts = [
        'auto_deploy' => 'boolean',
        'is_private' => 'boolean',
        'last_push_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deployKeys(): HasMany
    {
        return $this->hasMany(GitDeployKey::class, 'repository_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(GitWebhook::class, 'repository_id');
    }

    /**
     * Get the full repository path
     */
    public function getFullPathAttribute(): string
    {
        $account = $this->account;
        return "/home/{$account->username}/repositories/{$this->path}";
    }

    /**
     * Get the Git URL for SSH access
     */
    public function getSshUrlAttribute(): string
    {
        $account = $this->account;
        return "git@" . config('app.domain') . ":{$account->username}/{$this->name}.git";
    }

    /**
     * Get the Git URL for HTTPS access
     */
    public function getHttpsUrlAttribute(): string
    {
        $account = $this->account;
        $domain = config('app.domain');
        return "https://{$domain}/git/{$account->username}/{$this->name}.git";
    }

    /**
     * Get default deploy script based on detected project type
     */
    public static function getDefaultDeployScript(string $projectType): string
    {
        switch ($projectType) {
            case 'nodejs':
                return <<<'SCRIPT'
#!/bin/bash
set -e
npm ci --production
pm2 restart ecosystem.config.js || pm2 start ecosystem.config.js
SCRIPT;

            case 'python':
                return <<<'SCRIPT'
#!/bin/bash
set -e
source venv/bin/activate
pip install -r requirements.txt
supervisorctl restart app
SCRIPT;

            case 'php':
                return <<<'SCRIPT'
#!/bin/bash
set -e
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
SCRIPT;

            case 'static':
                return <<<'SCRIPT'
#!/bin/bash
set -e
# Static site - files are already deployed
echo "Deployment complete"
SCRIPT;

            default:
                return <<<'SCRIPT'
#!/bin/bash
set -e
echo "Deployment complete"
SCRIPT;
        }
    }

    /**
     * Get the short commit hash
     */
    public function getShortCommitHashAttribute(): ?string
    {
        return $this->last_commit_hash ? substr($this->last_commit_hash, 0, 7) : null;
    }
}
