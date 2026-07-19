<?php

namespace Tests\Feature\Modules\Avito;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AvitoQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Avito webhook payload with a non-"message" type so the request never
     * reaches AvitoMessageService (the actual funnel wiring is added
     * separately) — these tests exercise only the AvitoQuery authentication.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'id' => 'evt-1',
            'payload' => [
                'type' => 'read',
                'value' => [
                    'id' => 'm1',
                    'chat_id' => 'u2i-abc',
                    'author_id' => 7,
                    'content' => ['text' => 'hi'],
                ],
            ],
        ];
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        app(SettingsService::class)->set('avito.webhook_secret', 'topsecret');

        $this->postJson('/api/avito/bot/wrong', $this->payload())->assertForbidden();
    }

    public function test_accepts_request_with_correct_secret(): void
    {
        app(SettingsService::class)->set('avito.webhook_secret', 'topsecret');

        $this->postJson('/api/avito/bot/topsecret', $this->payload())->assertOk();
    }

    public function test_endpoint_is_open_when_no_secret_configured(): void
    {
        $this->postJson('/api/avito/bot', $this->payload())->assertOk();
    }

    public function test_rejection_body_does_not_leak_internal_details(): void
    {
        app(SettingsService::class)->set('avito.webhook_secret', 'topsecret');

        // The old catch-all echoed the caught exception, which leaked absolute
        // filesystem paths to the caller.
        $this->postJson('/api/avito/bot/wrong', $this->payload())
            ->assertForbidden()
            ->assertExactJson(['message' => 'Access is forbidden']);
    }

    public function test_a_broken_logger_does_not_reject_the_webhook(): void
    {
        // Reproduces the live incident: a root-owned daily log file made Monolog
        // throw, the catch-all turned it into 403, and Avito dropped the message.
        app(SettingsService::class)->set('avito.webhook_secret', 'topsecret');

        Log::shouldReceive('channel')->andThrow(new \RuntimeException('log file not writable'));

        $this->postJson('/api/avito/bot/topsecret', $this->payload())->assertOk();
    }

    public function test_unreadable_settings_return_bad_gateway_not_forbidden(): void
    {
        // Infrastructure failure must be retryable by the sender; 403 tells Avito
        // to give up on a message that was never actually rejected.
        $broken = \Mockery::mock(SettingsService::class);
        $broken->shouldReceive('get')
            ->with('avito.webhook_secret')
            ->andThrow(new \RuntimeException('settings store unavailable'));
        $this->app->instance(SettingsService::class, $broken);

        $this->postJson('/api/avito/bot/anything', $this->payload())
            ->assertStatus(Response::HTTP_BAD_GATEWAY);
    }
}
