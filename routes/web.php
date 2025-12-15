<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| FreePanel is a SPA (Single Page Application). All routes are handled
| by the React frontend. The Laravel backend serves only as an API.
|
*/

// Health check
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'version' => config('freepanel.version'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// SPA catch-all route - serve the React app
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
