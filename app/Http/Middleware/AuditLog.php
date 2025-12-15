<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog as AuditLogModel;

class AuditLog
{
    /**
     * Fields to exclude from logging.
     */
    protected array $excludeFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'private_key',
        'secret',
        'token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log non-GET requests
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            $this->logAction($request, $response);
        }

        return $response;
    }

    /**
     * Log the action.
     */
    protected function logAction(Request $request, Response $response): void
    {
        $user = $request->user();

        try {
            AuditLogModel::create([
                'user_id' => $user?->id,
                'account_id' => $user?->account?->id,
                'action' => $request->method() . ' ' . $request->path(),
                'resource_type' => $this->extractResourceType($request),
                'resource_id' => $request->route('id') ?? $request->route('account') ?? null,
                'new_values' => $this->filterSensitiveData($request->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the request if logging fails
            report($e);
        }
    }

    /**
     * Extract resource type from request path.
     */
    protected function extractResourceType(Request $request): string
    {
        $segments = $request->segments();

        // Remove 'api' and 'v1' prefixes
        $segments = array_values(array_filter($segments, fn($s) => !in_array($s, ['api', 'v1', 'admin', 'user'])));

        return $segments[0] ?? 'unknown';
    }

    /**
     * Filter sensitive data from request.
     */
    protected function filterSensitiveData(array $data): array
    {
        foreach ($this->excludeFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
