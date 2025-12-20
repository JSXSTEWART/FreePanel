<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Services\Zapier\ZapierWebhookService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZapierWebhookTest extends TestCase
{
    private ZapierWebhookService $zapierService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zapierService = app(ZapierWebhookService::class);

        // Mock the HTTP client for webhook calls
        Http::fake();
    }

    public function test_account_created_webhook_is_sent()
    {
        Config::set('zapier.enabled', true);
        Config::set('zapier.webhooks.account.created', 'https://hooks.zapier.com/test/account-created');

        $account = Account::factory()->create();

        event(new \App\Events\Account\AccountCreated($account));

        Http::assertSent(function ($request) use ($account) {
            return $request->url() === 'https://hooks.zapier.com/test/account-created'
                && $request->data()['event'] === 'account.created'
                && $request->data()['data']['id'] === $account->id
                && $request->data()['data']['username'] === $account->username;
        });
    }

    public function test_webhook_not_sent_when_disabled()
    {
        Config::set('zapier.enabled', false);

        $account = Account::factory()->create();

        event(new \App\Events\Account\AccountCreated($account));

        Http::assertNothingSent();
    }

    public function test_webhook_includes_timestamp()
    {
        Config::set('zapier.enabled', true);
        Config::set('zapier.webhooks.account.created', 'https://hooks.zapier.com/test/account-created');

        $account = Account::factory()->create();

        event(new \App\Events\Account\AccountCreated($account));

        Http::assertSent(function ($request) {
            return isset($request->data()['timestamp']);
        });
    }

    public function test_direct_webhook_send()
    {
        $result = $this->zapierService->send('account.created', [
            'id' => 123,
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        // This will fail because no webhook URL is configured, which is expected
        $this->assertFalse($result);
    }

    public function test_batch_webhook_send()
    {
        Config::set('zapier.enabled', true);
        Config::set('zapier.webhooks.account.created', 'https://hooks.zapier.com/test/1');
        Config::set('zapier.webhooks.domain.created', 'https://hooks.zapier.com/test/2');

        $events = [
            [
                'event' => 'account.created',
                'data' => ['id' => 1, 'username' => 'user1'],
            ],
            [
                'event' => 'domain.created',
                'data' => ['id' => 2, 'domain' => 'example.com'],
            ],
        ];

        $results = $this->zapierService->sendBatch($events);

        Http::assertSentCount(2);
    }
}
