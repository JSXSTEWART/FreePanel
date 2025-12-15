<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $feature
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Admins have access to all features
        if ($user->isAdmin()) {
            return $next($request);
        }

        $account = $user->account;

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'No hosting account associated with this user',
            ], 403);
        }

        if (!$account->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'message' => "Feature '{$feature}' is not enabled for this account",
            ], 403);
        }

        return $next($request);
    }
}
