<?php

namespace App\Listeners\Backup;

use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBackupCompletedWebhook implements ShouldQueue
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
     * @param object $event The backup completed event
     */
    public function handle($event): void
    {
        if (!isset($event->backup)) {
            return;
        }

        $payload = [
            'id' => $event->backup->id,
            'account_id' => $event->backup->account_id,
            'size_bytes' => $event->backup->size_bytes ?? 0,
            'completed_at' => now()->toIso8601String(),
        ];

        $this->webhookService->send('backup.completed', $payload);
    }
}
