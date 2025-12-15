<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DomainPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Domain $domain): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $domain->account->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $account = $user->account;
        if (!$account) {
            return false;
        }

        return $account->domains()->count() < $account->package->max_domains;
    }

    public function update(User $user, Domain $domain): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $domain->account->user_id === $user->id;
    }

    public function delete(User $user, Domain $domain): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($domain->is_main) {
            return false;
        }

        return $domain->account->user_id === $user->id;
    }
}
