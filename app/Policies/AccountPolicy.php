<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'reseller']);
    }

    public function view(User $user, Account $account): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('reseller')) {
            return $account->reseller_id === $user->reseller?->id;
        }

        return $account->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'reseller']);
    }

    public function update(User $user, Account $account): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('reseller')) {
            return $account->reseller_id === $user->reseller?->id;
        }

        return false;
    }

    public function delete(User $user, Account $account): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('reseller')) {
            return $account->reseller_id === $user->reseller?->id;
        }

        return false;
    }

    public function suspend(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }

    public function unsuspend(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }
}
