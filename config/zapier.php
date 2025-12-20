<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zapier Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Zapier webhook integration with FreePanel
    |
    */

    // Zapier MCP Embed Configuration
    'embed_id' => env('ZAPIER_EMBED_ID', '5728245c-97fc-4927-a279-1c8dcc0c526d'),
    'embed_secret' => env('ZAPIER_EMBED_SECRET', 'ZW7Iu7jUb78tRjCIzuC2O39rJG1HF-y1oYMQ6F_JPHE'),

    // Webhook URLs for different events
    'webhooks' => [
        'account.created' => env('ZAPIER_WEBHOOK_ACCOUNT_CREATED'),
        'account.suspended' => env('ZAPIER_WEBHOOK_ACCOUNT_SUSPENDED'),
        'account.updated' => env('ZAPIER_WEBHOOK_ACCOUNT_UPDATED'),
        'account.deleted' => env('ZAPIER_WEBHOOK_ACCOUNT_DELETED'),
        'domain.created' => env('ZAPIER_WEBHOOK_DOMAIN_CREATED'),
        'domain.deleted' => env('ZAPIER_WEBHOOK_DOMAIN_DELETED'),
        'ssl.expiring' => env('ZAPIER_WEBHOOK_SSL_EXPIRING'),
        'ssl.renewed' => env('ZAPIER_WEBHOOK_SSL_RENEWED'),
        'backup.completed' => env('ZAPIER_WEBHOOK_BACKUP_COMPLETED'),
        'backup.failed' => env('ZAPIER_WEBHOOK_BACKUP_FAILED'),
        'quota.exceeded' => env('ZAPIER_WEBHOOK_QUOTA_EXCEEDED'),
    ],

    // Webhook signature verification settings (HMAC-SHA256)
    'signature' => [
        // Enable HMAC-SHA256 signature verification for incoming webhooks
        'enabled' => env('ZAPIER_WEBHOOK_SIGNATURE_ENABLED', true),

        // Shared secret for signature verification
        // Generate with: php artisan tinker -> WebhookSignatureService::generateSecret()
        'secret' => env('ZAPIER_WEBHOOK_SECRET'),

        // Maximum age of webhook in seconds (prevents replay attacks)
        'timestamp_tolerance' => env('ZAPIER_WEBHOOK_TIMESTAMP_TOLERANCE', 300), // 5 minutes

        // HMAC algorithm
        'algorithm' => 'sha256',
    ],

    // Webhook formatting and transmission settings
    'format' => env('ZAPIER_WEBHOOK_FORMAT', 'json'),
    'timeout' => env('ZAPIER_WEBHOOK_TIMEOUT', 10),
    'retry_count' => env('ZAPIER_WEBHOOK_RETRY_COUNT', 3),

    // Batch webhook settings
    'batch' => [
        'enabled' => true,
        'max_size' => 100,
    ],

    // Enable/disable Zapier integration globally
    'enabled' => env('ZAPIER_ENABLED', true),
];
