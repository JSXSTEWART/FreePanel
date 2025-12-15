<?php

namespace App\Policies;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmailAccount $emailAccount): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $emailAccount->domain->account->user_id === $user->id;
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

        $currentCount = EmailAccount::whereHas('domain', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })->count();

        return $currentCount < $account->package->max_email_accounts;
    }

    public function update(User $user, EmailAccount $emailAccount): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $emailAccount->domain->account->user_id === $user->id;
    }

    public function delete(User $user, EmailAccount $emailAccount): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $emailAccount->domain->account->user_id === $user->id;
    }
}
