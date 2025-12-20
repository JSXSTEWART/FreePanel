<?php

namespace App\Listeners\Backup;

use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBackupFailedWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected ZapierWebhookService $webhookService
    ) {}

    /**
     * Handle the event.
     *
     * @param object $event The backup failed event
     */
    public function handle($event): void
    {
        if (!isset($event->backup)) {
            return;
        }

        $payload = [
            'id' => $event->backup->id,
            'account_id' => $event->backup->account_id,
            'error' => $event->error ?? 'Backup failed without specific error message',
            'failed_at' => now()->toIso8601String(),
        ];

        $this->webhookService->send('backup.failed', $payload);
    }
}
