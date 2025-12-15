<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckQuota
{
    /**
     * Quota mapping: resource => [package_field, count_method]
     */
    protected array $quotaMap = [
        'domains' => ['max_domains', 'domains'],
        'subdomains' => ['max_subdomains', 'subdomains'],
        'email_accounts' => ['max_email_accounts', 'emailAccountsCount'],
        'databases' => ['max_databases', 'databases'],
        'ftp_accounts' => ['max_ftp_accounts', 'ftpAccounts'],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $resource
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Admins bypass quota checks
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

        // Only check quota for POST requests (creating new resources)
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        if (!isset($this->quotaMap[$resource])) {
            return $next($request);
        }

        [$limitField, $countMethod] = $this->quotaMap[$resource];
        $package = $account->package;
        $limit = $package->{$limitField};

        // 0 means unlimited
        if ($limit === 0) {
            return $next($request);
        }

        // Get current count
        $currentCount = is_string($countMethod) && method_exists($account, $countMethod)
            ? (is_callable([$account, $countMethod]) ? $account->{$countMethod}() : $account->{$countMethod}()->count())
            : $account->{$countMethod}()->count();

        if ($currentCount >= $limit) {
            return response()->json([
                'success' => false,
                'message' => "Quota exceeded for {$resource}. Limit: {$limit}, Current: {$currentCount}",
            ], 403);
        }

        return $next($request);
    }
}
