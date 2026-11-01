<?php

namespace Tests\Feature\Modules\Ok;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use App\Modules\Telegram\Jobs\SendOkTelegramMessageJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OkWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/ok/bot/right-secret';

    private const CHAT_ID = 'chat:C3ecb9d02a600';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        app(SettingsService::class)->set('ok.webhook_secret', 'right-secret');
    }

    /**
     * @return array<string, mixed>
     */
    private function stub(string $name): array
    {
        return json_decode((string) file_get_contents(base_path("tests/Stubs/Ok/{$name}.json")), true);
    }

    public function test_wrong_secret_is_rejected_and_not_processed(): void
    {
        $this->postJson('/api/ok/bot/wrong', $this->stub('message_created'))->assertForbidden();

        $this->assertDatabaseMissing('bot_users', ['chat_id' => self::CHAT_ID]);
    }

    public function test_empty_configured_secret_rejects_every_request(): void
    {
        app(SettingsService::class)->forget('ok.webhook_secret');

        $this->postJson(self::URL, $this->stub('message_created'))->assertForbidden();

        $this->assertDatabaseMissing('bot_users', ['chat_id' => self::CHAT_ID]);
    }

    public function test_message_goes_to_the_topic_when_group_is_configured(): void
    {
        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();

        $this->assertDatabaseHas('bot_users', [
            'chat_id' => self::CHAT_ID,
            'platform' => 'ok',
            'display_name' => 'Тест Тестов',
        ]);
        Queue::assertPushed(SendOkTelegramMessageJob::class, fn ($job) => $job->queryParams->text === 'Здравствуйте');
        $this->assertDatabaseMissing('messages', ['text' => 'Здравствуйте']);
    }

    public function test_message_is_saved_for_admin_panel_without_group(): void
    {
        app(SettingsService::class)->forget('telegram.group_id');

        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();

        $this->assertDatabaseHas('messages', [
            'platform' => 'ok',
            'message_type' => 'incoming',
            'from_id' => 123456789012,
            'text' => 'Здравствуйте',
        ]);
        Queue::assertNotPushed(SendOkTelegramMessageJob::class);
    }

    public function test_redelivered_event_is_processed_once(): void
    {
        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();
        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();

        Queue::assertPushed(SendOkTelegramMessageJob::class, 1);
    }

    public function test_attachment_only_message_is_forwarded_as_a_file(): void
    {
        $this->postJson(self::URL, $this->stub('message_attachment_only'))->assertOk();

        Queue::assertPushed(SendOkTelegramMessageJob::class, fn ($job) => $job->attachment->method === 'sendPhoto'
            && $job->attachment->url === 'https://i.okcdn.ru/i?id=1');
    }

    public function test_banned_user_gets_ban_notice_instead_of_forwarding(): void
    {
        $botUser = BotUser::getUserByChatId(self::CHAT_ID, 'ok');
        $botUser->update(['is_banned' => true, 'banned_at' => now()]);

        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->text === __('messages.ban_user'));
        Queue::assertNotPushed(SendOkTelegramMessageJob::class);
    }

    public function test_chat_started_sends_greeting(): void
    {
        $this->postJson(self::URL, $this->stub('chat_started'))->assertOk();

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->text === __('messages.start'));
    }

    public function test_unknown_event_type_is_acknowledged_and_ignored(): void
    {
        $payload = $this->stub('message_created');
        $payload['webhookType'] = 'MESSAGE_CALLBACK';

        $this->postJson(self::URL, $payload)->assertOk();

        $this->assertDatabaseMissing('bot_users', ['chat_id' => self::CHAT_ID]);
    }

    public function test_payload_without_chat_is_acknowledged(): void
    {
        $this->postJson(self::URL, ['webhookType' => 'MESSAGE_CREATED'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_ai_draft_is_dispatched_when_ai_is_enabled(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('ai.enabled', true);
        $settings->set('ai.auto_reply', false);

        $this->postJson(self::URL, $this->stub('message_created'))->assertOk();

        Queue::assertPushed(SendAiDraftJob::class, fn ($job) => $job->updateDto === null && $job->userMessage === 'Здравствуйте');
        Queue::assertNotPushed(SendAiReplyJob::class);
    }
}
