<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'domain_id',
        'name',
        'type',
        'runtime_version',
        'path',
        'entry_point',
        'startup_file',
        'port',
        'environment_variables',
        'status',
        'process_manager',
        'instances',
        'max_memory_mb',
        'auto_restart',
        'watch_files',
        'log_path',
        'error_log_path',
        'last_error',
        'started_at',
    ];

    protected $casts = [
        'environment_variables' => 'array',
        'auto_restart' => 'boolean',
        'watch_files' => 'boolean',
        'started_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApplicationLog::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ApplicationMetric::class);
    }

    /**
     * Get available runtime versions
     */
    public static function availableRuntimes(): array
    {
        return [
            'nodejs' => [
                '22.x' => 'Node.js 22.x LTS',
                '20.x' => 'Node.js 20.x LTS',
                '18.x' => 'Node.js 18.x LTS',
            ],
            'python' => [
                '3.12' => 'Python 3.12',
                '3.11' => 'Python 3.11',
                '3.10' => 'Python 3.10',
            ],
            'ruby' => [
                '3.3' => 'Ruby 3.3',
                '3.2' => 'Ruby 3.2',
                '3.1' => 'Ruby 3.1',
            ],
            'php' => [
                '8.4' => 'PHP 8.4',
                '8.3' => 'PHP 8.3',
                '8.2' => 'PHP 8.2',
            ],
        ];
    }

    /**
     * Get the full application path
     */
    public function getFullPathAttribute(): string
    {
        $account = $this->account;
        return "/home/{$account->username}/{$this->path}";
    }

    /**
     * Generate PM2 ecosystem config for Node.js
     */
    public function toPm2Config(): array
    {
        return [
            'name' => $this->name,
            'script' => $this->entry_point,
            'cwd' => $this->full_path,
            'instances' => $this->instances,
            'exec_mode' => $this->instances > 1 ? 'cluster' : 'fork',
            'max_memory_restart' => $this->max_memory_mb ? "{$this->max_memory_mb}M" : null,
            'watch' => $this->watch_files,
            'autorestart' => $this->auto_restart,
            'env' => $this->environment_variables ?? [],
            'error_file' => $this->error_log_path ?? "{$this->full_path}/logs/error.log",
            'out_file' => $this->log_path ?? "{$this->full_path}/logs/out.log",
        ];
    }

    /**
     * Generate Gunicorn config for Python
     */
    public function toGunicornConfig(): string
    {
        $config = "# Gunicorn configuration\n";
        $config .= "bind = '127.0.0.1:{$this->port}'\n";
        $config .= "workers = {$this->instances}\n";
        $config .= "worker_class = 'sync'\n";

        if ($this->max_memory_mb) {
            $config .= "# Memory limit: {$this->max_memory_mb}MB (enforced by process manager)\n";
        }

        $config .= "errorlog = '{$this->error_log_path}'\n";
        $config .= "accesslog = '{$this->log_path}'\n";
        $config .= "capture_output = True\n";

        return $config;
    }

    /**
     * Generate Apache proxy configuration
     */
    public function toApacheProxyConfig(): string
    {
        $config = "# Proxy configuration for {$this->name}\n";
        $config .= "<Location />\n";
        $config .= "    ProxyPass http://127.0.0.1:{$this->port}/\n";
        $config .= "    ProxyPassReverse http://127.0.0.1:{$this->port}/\n";
        $config .= "</Location>\n";

        return $config;
    }

    /**
     * Check if application is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get uptime in human readable format
     */
    public function getUptimeAttribute(): ?string
    {
        if (!$this->started_at || $this->status !== 'running') {
            return null;
        }

        $diff = $this->started_at->diff(now());

        if ($diff->days > 0) {
            return "{$diff->days}d {$diff->h}h";
        } elseif ($diff->h > 0) {
            return "{$diff->h}h {$diff->i}m";
        } else {
            return "{$diff->i}m {$diff->s}s";
        }
    }

    /**
     * Get default entry point based on type
     */
    public static function getDefaultEntryPoint(string $type): string
    {
        return match ($type) {
            'nodejs' => 'app.js',
            'python' => 'app:app',
            'ruby' => 'config.ru',
            'php' => 'public/index.php',
            default => 'app.js',
        };
    }

    /**
     * Get process manager based on type
     */
    public static function getDefaultProcessManager(string $type): string
    {
        return match ($type) {
            'nodejs' => 'pm2',
            'python' => 'gunicorn',
            'ruby' => 'passenger',
            'php' => 'php-fpm',
            default => 'pm2',
        };
    }
}
