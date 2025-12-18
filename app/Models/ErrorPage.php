<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorPage extends Model
{
    protected $fillable = [
        'account_id',
        'domain_id',
        'error_code',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get supported error codes
     */
    public static function getSupportedCodes(): array
    {
        return [
            400 => 'Bad Request',
            401 => 'Authorization Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
    }

    /**
     * Get default content for an error code
     */
    public static function getDefaultContent(int $code): string
    {
        $messages = self::getSupportedCodes();
        $message = $messages[$code] ?? 'Error';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$code} - {$message}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
        h1 { font-size: 72px; margin-bottom: 0; color: #333; }
        p { font-size: 24px; color: #666; }
    </style>
</head>
<body>
    <h1>{$code}</h1>
    <p>{$message}</p>
</body>
</html>
HTML;
    }
}
