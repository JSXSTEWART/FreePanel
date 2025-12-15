<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\Database;
use App\Models\Account;
use App\Policies\DomainPolicy;
use App\Policies\EmailPolicy;
use App\Policies\DatabasePolicy;
use App\Policies\AccountPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Domain::class => DomainPolicy::class,
        EmailAccount::class => EmailPolicy::class,
        Database::class => DatabasePolicy::class,
        Account::class => AccountPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define admin gate
        Gate::define('admin', function ($user) {
            return $user->role === 'admin';
        });

        // Define reseller gate
        Gate::define('reseller', function ($user) {
            return in_array($user->role, ['admin', 'reseller']);
        });

        // Define ability to manage an account
        Gate::define('manage-account', function ($user, $account) {
            if ($user->role === 'admin') {
                return true;
            }

            if ($user->role === 'reseller') {
                return $account->user->parent_id === $user->id;
            }

            return $account->user_id === $user->id;
        });
    }
}
