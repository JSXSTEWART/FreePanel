<?php

namespace App\Policies;

use App\Models\Database;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DatabasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Database $database): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $database->account->user_id === $user->id;
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

        return $account->databases()->count() < $account->package->max_databases;
    }

    public function update(User $user, Database $database): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $database->account->user_id === $user->id;
    }

    public function delete(User $user, Database $database): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $database->account->user_id === $user->id;
    }
}
