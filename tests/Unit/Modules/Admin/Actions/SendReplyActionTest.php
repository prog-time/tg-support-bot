<?php

namespace Tests\Unit\Modules\Admin\Actions;

use App\Models\BotUser;
use App\Models\ExternalSource;
use App\Models\ExternalUser;
use App\Modules\Admin\Actions\SendReplyAction;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_outgoing_message_for_telegram_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        SendReplyAction::execute($botUser, 'Hello Telegram');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'text' => 'Hello Telegram',
        ]);
    }

    public function test_dispatches_telegram_job_for_telegram_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        SendReplyAction::execute($botUser, 'Hello Telegram');

        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendVkSimpleMessageJob::class);
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_saves_outgoing_message_for_vk_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 200, 'platform' => 'vk']);

        SendReplyAction::execute($botUser, 'Hello VK');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'vk',
            'message_type' => 'outgoing',
            'text' => 'Hello VK',
        ]);
    }

    public function test_dispatches_vk_job_for_vk_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 200, 'platform' => 'vk']);

        SendReplyAction::execute($botUser, 'Hello VK');

        Queue::assertPushed(SendVkSimpleMessageJob::class);
        Queue::assertNotPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_dispatches_webhook_for_external_user_with_webhook_url(): void
    {
        Queue::fake();

        ExternalSource::create(['name' => 'widget', 'webhook_url' => 'https://example.com/hook']);
        $externalUser = ExternalUser::create(['external_id' => 'ext-1', 'source' => 'widget']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'platform' => 'widget']);

        SendReplyAction::execute($botUser, 'Hello External');

        Queue::assertPushed(SendWebhookMessage::class, function (SendWebhookMessage $job): bool {
            return $job->url === 'https://example.com/hook'
                && $job->payload['message']['text'] === 'Hello External';
        });
    }

    public function test_does_not_dispatch_webhook_when_no_webhook_url(): void
    {
        Queue::fake();

        ExternalSource::create(['name' => 'widget', 'webhook_url' => '']);
        $externalUser = ExternalUser::create(['external_id' => 'ext-2', 'source' => 'widget']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'platform' => 'widget']);

        SendReplyAction::execute($botUser, 'Hello');

        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_does_not_dispatch_webhook_when_external_source_missing(): void
    {
        Queue::fake();

        $externalUser = ExternalUser::create(['external_id' => 'ext-3', 'source' => 'unknown_source']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'platform' => 'unknown_source']);

        SendReplyAction::execute($botUser, 'Hello');

        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_webhook_payload_contains_correct_structure(): void
    {
        Queue::fake();

        ExternalSource::create(['name' => 'crm', 'webhook_url' => 'https://crm.example.com/hook']);
        $externalUser = ExternalUser::create(['external_id' => 'crm-user-1', 'source' => 'crm']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'platform' => 'crm']);

        SendReplyAction::execute($botUser, 'Test message');

        Queue::assertPushed(SendWebhookMessage::class, function (SendWebhookMessage $job) use ($externalUser): bool {
            return $job->payload['type_query'] === 'send_message'
                && $job->payload['externalId'] === $externalUser->external_id
                && $job->payload['message']['content_type'] === 'text'
                && $job->payload['message']['message_type'] === 'outgoing'
                && $job->payload['message']['text'] === 'Test message';
        });
    }
}
