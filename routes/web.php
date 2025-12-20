<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\OAuthController;

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

// OAuth callback route (web route for browser redirects)
Route::get('/auth/callback', function () {
    // Extract provider from state or use default
    $provider = request('state') ? 'google' : 'google'; // This is a simplified version
    
    // Build redirect URL to the React app with the auth data
    $params = http_build_query(request()->all());
    
    return redirect(config('app.frontend_url', config('app.url')) . '/auth/callback?' . $params);
});

// SPA catch-all route - serve the React app
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
