<?php

namespace Tests\Unit\Modules\Admin\Actions;

use App\Models\BotUser;
use App\Models\ExternalSource;
use App\Models\ExternalUser;
use App\Models\User;
use App\Modules\Admin\Actions\SendReplyAction;
use App\Modules\Admin\Jobs\MirrorAdminReplyToGroupJob;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Max\Actions\UploadFileMax;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SendReplyActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

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

    public function test_dispatches_vk_file_job_with_attachment_for_vk_user(): void
    {
        Queue::fake();

        Http::fake([
            'api.vk.com/method/docs.getMessagesUploadServer*' => Http::response([
                'response' => ['upload_url' => 'https://vk-upload.test/upload'],
            ]),
            'vk-upload.test/upload' => Http::response(['file' => 'uploaded-file-data']),
            'api.vk.com/method/docs.save*' => Http::response([
                'response' => ['doc' => ['owner_id' => 111, 'id' => 222]],
            ]),
        ]);

        $botUser = BotUser::create(['chat_id' => 210, 'platform' => 'vk']);
        $file = UploadedFile::fake()->create('doc.pdf', 10);

        SendReplyAction::execute($botUser, 'caption', $file);

        // The uploaded doc reference is attached to the outgoing VK message.
        Queue::assertPushed(SendVkSimpleMessageJob::class, function (SendVkSimpleMessageJob $job): bool {
            return $job->queryParams->attachment === 'doc111_222';
        });
    }

    public function test_records_local_attachment_for_vk_file_reply(): void
    {
        Queue::fake();
        Storage::fake('local');

        Http::fake([
            'api.vk.com/method/docs.getMessagesUploadServer*' => Http::response([
                'response' => ['upload_url' => 'https://vk-upload.test/upload'],
            ]),
            'vk-upload.test/upload' => Http::response(['file' => 'uploaded-file-data']),
            'api.vk.com/method/docs.save*' => Http::response([
                'response' => ['doc' => ['owner_id' => 111, 'id' => 222]],
            ]),
        ]);

        $botUser = BotUser::create(['chat_id' => 211, 'platform' => 'vk']);
        $file = UploadedFile::fake()->create('report.pdf', 10);

        SendReplyAction::execute($botUser, '', $file);

        // Regression: the outgoing file must be stored on the private disk and
        // recorded by its path so the admin chat workspace can render it instead
        // of showing only the «Вложение» placeholder.
        $attachment = \App\Models\MessageAttachment::where('file_name', 'report.pdf')->first();
        $this->assertNotNull($attachment);
        $this->assertStringStartsWith('chat-attachments/', (string) $attachment->file_id);
        Storage::disk('local')->assertExists($attachment->file_id);
    }

    public function test_saves_outgoing_message_for_max_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 400, 'platform' => 'max']);

        SendReplyAction::execute($botUser, 'Hello MAX');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'max',
            'message_type' => 'outgoing',
            'text' => 'Hello MAX',
        ]);
    }

    public function test_dispatches_max_text_job_for_max_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 401, 'platform' => 'max']);

        SendReplyAction::execute($botUser, 'Hello MAX');

        Queue::assertPushed(SendMaxSimpleMessageJob::class, function (SendMaxSimpleMessageJob $job): bool {
            return $job->queryParams->methodQuery === 'sendMessage'
                && $job->queryParams->text === 'Hello MAX'
                && $job->queryParams->user_id === 401;
        });
        // MAX must no longer fall through to the external-webhook path.
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_dispatches_max_file_job_with_token_for_max_user(): void
    {
        Queue::fake();
        Storage::fake('local');

        // Mock the uploader so the test never hits MAX's CDN.
        $upload = Mockery::mock(UploadFileMax::class);
        $upload->shouldReceive('uploadContents')->once()->andReturn('tok_abc');
        $this->app->instance(UploadFileMax::class, $upload);

        $botUser = BotUser::create(['chat_id' => 402, 'platform' => 'max']);
        $file = UploadedFile::fake()->create('photo.jpg', 10);

        SendReplyAction::execute($botUser, 'caption', $file);

        Queue::assertPushed(SendMaxSimpleMessageJob::class, function (SendMaxSimpleMessageJob $job): bool {
            return in_array($job->queryParams->methodQuery, ['sendImage', 'sendAudio', 'sendFile'], true)
                && $job->queryParams->file_token === 'tok_abc'
                && $job->queryParams->text === 'caption';
        });

        // The outgoing file is stored on the private disk and recorded by its path
        // so the admin thread serves it through the chat-attachment route.
        $attachment = \App\Models\MessageAttachment::where('file_name', 'photo.jpg')->first();
        $this->assertNotNull($attachment);
        $this->assertStringStartsWith('chat-attachments/', (string) $attachment->file_id);
        Storage::disk('local')->assertExists($attachment->file_id);
    }

    public function test_max_file_upload_failure_falls_back_to_text(): void
    {
        Queue::fake();

        $upload = Mockery::mock(UploadFileMax::class);
        $upload->shouldReceive('uploadContents')->andReturn(null);
        $this->app->instance(UploadFileMax::class, $upload);

        $botUser = BotUser::create(['chat_id' => 403, 'platform' => 'max']);
        $file = UploadedFile::fake()->create('doc.pdf', 10);

        SendReplyAction::execute($botUser, 'fallback text', $file);

        Queue::assertPushed(SendMaxSimpleMessageJob::class, function (SendMaxSimpleMessageJob $job): bool {
            return $job->queryParams->methodQuery === 'sendMessage'
                && $job->queryParams->text === 'fallback text';
        });
    }

    // ── Avito ──────────────────────────────────────────────────────────────────

    public function test_saves_outgoing_message_for_avito_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'u2i-700', 'platform' => 'avito']);

        SendReplyAction::execute($botUser, 'Hello Avito');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'avito',
            'message_type' => 'outgoing',
            'text' => 'Hello Avito',
        ]);
    }

    public function test_dispatches_avito_simple_job_with_chat_id_and_text_for_avito_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'u2i-701', 'platform' => 'avito']);

        SendReplyAction::execute($botUser, 'Hello Avito');

        Queue::assertPushed(SendAvitoSimpleMessageJob::class, function (SendAvitoSimpleMessageJob $job) use ($botUser): bool {
            return $job->queryParams->methodQuery === 'sendMessage'
                && $job->queryParams->chat_id === $botUser->chat_id
                && $job->queryParams->text === 'Hello Avito';
        });
        Queue::assertNotPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_avito_file_attachment_is_skipped_with_warning_but_text_still_sent(): void
    {
        Queue::fake();

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with('SendReplyAction: Avito does not support file replies, attachment skipped', Mockery::type('array'));

        $botUser = BotUser::create(['chat_id' => 'u2i-702', 'platform' => 'avito']);
        $file = UploadedFile::fake()->create('doc.pdf', 10);

        SendReplyAction::execute($botUser, 'Text with skipped file', $file);

        Queue::assertPushed(SendAvitoSimpleMessageJob::class, function (SendAvitoSimpleMessageJob $job): bool {
            return $job->queryParams->text === 'Text with skipped file';
        });
    }

    public function test_avito_empty_text_and_file_dispatches_nothing(): void
    {
        Queue::fake();

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $botUser = BotUser::create(['chat_id' => 'u2i-703', 'platform' => 'avito']);
        $file = UploadedFile::fake()->create('doc.pdf', 10);

        SendReplyAction::execute($botUser, '', $file);

        Queue::assertNotPushed(SendAvitoSimpleMessageJob::class);
    }

    public function test_avito_empty_text_without_file_dispatches_nothing(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'u2i-704', 'platform' => 'avito']);

        SendReplyAction::execute($botUser, '');

        Queue::assertNotPushed(SendAvitoSimpleMessageJob::class);
    }

    // ── Email ──────────────────────────────────────────────────────────────────

    public function test_saves_outgoing_message_for_email_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'user@example.com', 'platform' => 'email']);

        SendReplyAction::execute($botUser, 'Hello Email');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'email',
            'message_type' => 'outgoing',
            'text' => 'Hello Email',
        ]);
    }

    public function test_dispatches_email_simple_job_with_chat_id_and_text_for_email_user(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'user2@example.com', 'platform' => 'email']);

        SendReplyAction::execute($botUser, 'Hello Email');

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job) use ($botUser): bool {
            return $job->queryParams->to === $botUser->chat_id
                && $job->queryParams->text === 'Hello Email';
        });
        Queue::assertNotPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_email_file_attachment_is_skipped_with_warning_but_text_still_sent(): void
    {
        Queue::fake();

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with('SendReplyAction: Email does not support file replies yet, attachment skipped', Mockery::type('array'));

        $botUser = BotUser::create(['chat_id' => 'user3@example.com', 'platform' => 'email']);
        $file = UploadedFile::fake()->create('doc.pdf', 10);

        SendReplyAction::execute($botUser, 'Text with skipped file', $file);

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job): bool {
            return $job->queryParams->text === 'Text with skipped file';
        });
    }

    public function test_email_empty_text_without_file_dispatches_nothing(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 'user4@example.com', 'platform' => 'email']);

        SendReplyAction::execute($botUser, '');

        Queue::assertNotPushed(SendEmailMessageJob::class);
    }

    public function test_dispatches_webhook_for_external_user_with_webhook_url(): void
    {
        Queue::fake();

        ExternalSource::create(['name' => 'widget', 'webhook_url' => 'https://example.com/hook']);
        $externalUser = ExternalUser::create(['external_id' => 'ext-1', 'source' => 'widget']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'external_user_id' => $externalUser->id, 'platform' => 'widget']);

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
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'external_user_id' => $externalUser->id, 'platform' => 'widget']);

        SendReplyAction::execute($botUser, 'Hello');

        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_does_not_dispatch_webhook_when_external_source_missing(): void
    {
        Queue::fake();

        $externalUser = ExternalUser::create(['external_id' => 'ext-3', 'source' => 'unknown_source']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'external_user_id' => $externalUser->id, 'platform' => 'unknown_source']);

        SendReplyAction::execute($botUser, 'Hello');

        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_webhook_payload_contains_correct_structure(): void
    {
        Queue::fake();

        ExternalSource::create(['name' => 'crm', 'webhook_url' => 'https://crm.example.com/hook']);
        $externalUser = ExternalUser::create(['external_id' => 'crm-user-1', 'source' => 'crm']);
        $botUser = BotUser::create(['chat_id' => $externalUser->id, 'external_user_id' => $externalUser->id, 'platform' => 'crm']);

        SendReplyAction::execute($botUser, 'Test message');

        Queue::assertPushed(SendWebhookMessage::class, function (SendWebhookMessage $job) use ($externalUser): bool {
            return $job->payload['type_query'] === 'send_message'
                && $job->payload['externalId'] === $externalUser->external_id
                && $job->payload['message']['content_type'] === 'text'
                && $job->payload['message']['message_type'] === 'outgoing'
                && $job->payload['message']['text'] === 'Test message';
        });
    }

    public function test_reopens_closed_conversation_on_reply(): void
    {
        Queue::fake();

        $botUser = BotUser::create([
            'chat_id' => 300,
            'platform' => 'telegram',
            'is_closed' => true,
            'closed_at' => now()->subDay(),
        ]);

        SendReplyAction::execute($botUser, 'Reopening reply');

        $botUser->refresh();
        $this->assertFalse($botUser->isClosed());
        $this->assertNull($botUser->closed_at);
    }

    public function test_does_not_touch_open_conversation_on_reply(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 301, 'platform' => 'telegram', 'is_closed' => false]);

        SendReplyAction::execute($botUser, 'Regular reply');

        $botUser->refresh();
        $this->assertFalse($botUser->isClosed());
    }

    // ── Authorship ─────────────────────────────────────────────────────────────

    public function test_passing_author_writes_sender_user_id_and_sender_name(): void
    {
        Queue::fake();

        $user = User::factory()->create(['name' => 'Operator Vasya']);
        $botUser = BotUser::create(['chat_id' => 500, 'platform' => 'telegram']);

        SendReplyAction::execute($botUser, 'Authored reply', null, $user);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'sender_user_id' => $user->id,
            'sender_name' => 'Operator Vasya',
        ]);
    }

    public function test_omitting_author_leaves_sender_fields_null(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 501, 'platform' => 'telegram']);

        SendReplyAction::execute($botUser, 'Anonymous reply');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'sender_user_id' => null,
            'sender_name' => null,
        ]);
    }

    public function test_explicit_null_author_preserves_backward_compatibility(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 502, 'platform' => 'vk']);

        SendReplyAction::execute($botUser, 'Compat reply', null, null);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'sender_user_id' => null,
            'sender_name' => null,
        ]);
    }

    // ── Supergroup mirror ──────────────────────────────────────────────────────

    public function test_mirrors_reply_to_group_when_telegram_configured(): void
    {
        Queue::fake();

        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->set('telegram.token', 'main_token');
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-100111222333');

        $botUser = BotUser::create(['chat_id' => 600, 'platform' => 'telegram', 'topic_id' => 10]);

        SendReplyAction::execute($botUser, 'Mirrored reply');

        Queue::assertPushed(MirrorAdminReplyToGroupJob::class, function (MirrorAdminReplyToGroupJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->text === 'Mirrored reply';
        });

        // Only one messages row (not two).
        $this->assertSame(1, \App\Models\Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_does_not_mirror_to_group_when_telegram_not_configured(): void
    {
        Queue::fake();

        // Telegram channel not configured — no token/secret set.
        app(\App\Services\Settings\SettingsService::class)->set('telegram.token', null);
        app(\App\Services\Settings\SettingsService::class)->set('telegram.secret_key', null);

        $botUser = BotUser::create(['chat_id' => 601, 'platform' => 'vk']);

        SendReplyAction::execute($botUser, 'VK reply, no mirror');

        Queue::assertNotPushed(MirrorAdminReplyToGroupJob::class);
    }

    public function test_mirror_uses_placeholder_text_for_file_only_reply(): void
    {
        Queue::fake();
        Storage::fake('local');

        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->set('telegram.token', 'main_token');
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-100111222333');

        $botUser = BotUser::create(['chat_id' => 602, 'platform' => 'telegram', 'topic_id' => 11]);

        SendReplyAction::execute($botUser, '');

        Queue::assertPushed(MirrorAdminReplyToGroupJob::class, function (MirrorAdminReplyToGroupJob $job): bool {
            // The «Ответ из админки:» label + newline is added by the job's prefix.
            return $job->text === '[вложение]' && $job->prefix === "👤 Ответ из админки:\n";
        });
    }
}
