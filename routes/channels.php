<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private channel for account-specific updates
Broadcast::channel('freepanel.account.{accountId}', function ($user, $accountId) {
    return $user->account?->id === (int) $accountId;
});

// Admin notifications channel
Broadcast::channel('freepanel.admin', function ($user) {
    return $user->role === 'admin';
});

// Service status channel (admin only)
Broadcast::channel('freepanel.services', function ($user) {
    return $user->role === 'admin';
});
