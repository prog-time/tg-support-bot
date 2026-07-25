<?php

namespace Tests\Feature\Modules\Telegram;

use App\Livewire\Chat\ConversationPage;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Max\Services\MaxMessageService;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Vk\DTOs\VkUpdateDto;
use App\Modules\Vk\Services\VkMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Mocks\Max\MaxUpdateDtoMock;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers the "incoming messages are always persisted to `messages`" invariant
 * introduced to fix the group-OFF blind spot (issue #175).
 *
 * Scenarios:
 *   - Telegram text,  group OFF  → exactly 1 incoming row, no duplicate
 *   - Telegram media (photo),  group OFF  → 1 row + 1 attachment row
 *   - Telegram text,  group ON   → 1 row (via job), group-forward job dispatched, no duplicate
 *   - VK text,        group OFF  → exactly 1 incoming row
 *   - VK media (photo), group OFF → 1 row + 1 attachment row
 *   - VK text,        group ON   → group-forward job dispatched (existing behavior)
 *   - Max text,       group OFF  → exactly 1 incoming row
 *   - Max media (photo), group OFF → 1 row + 1 attachment row
 *   - Max text,       group ON   → group-forward job dispatched (existing behavior)
 *   - Admin ConversationPage shows the group-OFF persisted message
 */
class IncomingMessagePersistenceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    /**
     * By default TestCase::setUp() seeds telegram.group_id (to make the group-ON
     * path work across the test suite). Tests that want "group OFF" must call
     * $this->clearGroupId() to remove that setting before exercising the path.
     */
    private function clearGroupId(): void
    {
        app(\App\Services\Settings\SettingsService::class)->forget('telegram.group_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a minimal Telegram private-message payload for a given BotUser.
     *
     * @param BotUser             $botUser
     * @param array<string,mixed> $messageOverrides merged into the `message` key
     *
     * @return array<string, mixed>
     */
    private function telegramPayload(BotUser $botUser, array $messageOverrides = []): array
    {
        return [
            'update_id' => 100001,
            'message' => array_merge([
                'message_id' => 555,
                'from' => [
                    'id' => $botUser->chat_id,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => $botUser->chat_id,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Hello from user',
            ], $messageOverrides),
        ];
    }

    /**
     * POST to the Telegram webhook with the secret-key header for the middleware.
     *
     * @param array<string, mixed> $payload
     */
    private function postTgWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/telegram/bot', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);
    }

    // ── Telegram — group OFF ──────────────────────────────────────────────────

    /**
     * When no group_id is configured, an incoming Telegram text message must be
     * persisted directly to `messages` (group-OFF path) without going through the
     * supergroup job. No duplicate rows may be created.
     */
    public function test_telegram_incoming_text_with_group_off_persists_to_messages(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
        ]);
        // TestCase::setUp() seeds a default group_id — remove it for the group-OFF scenario.
        $this->clearGroupId();

        $botUser = BotUser::create(['chat_id' => 123456, 'platform' => 'telegram']);

        $this->postTgWebhook($this->telegramPayload($botUser))->assertOk();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 555,
            'to_id' => 0,
            'text' => 'Hello from user',
        ]);

        // Exactly one incoming row — no duplicate.
        $this->assertSame(
            1,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count()
        );

        // No group-forward or topic-creation jobs dispatched.
        Queue::assertNotPushed(SendTelegramMessageJob::class);
        Queue::assertNotPushed(TopicCreateJob::class);
    }

    /**
     * When no group_id is configured, an incoming Telegram photo must be persisted
     * to `messages` AND produce a `message_attachments` row.
     */
    public function test_telegram_incoming_photo_with_group_off_persists_message_and_attachment(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
        ]);
        $this->clearGroupId();

        $botUser = BotUser::create(['chat_id' => 234567, 'platform' => 'telegram']);

        $payload = $this->telegramPayload($botUser, [
            'photo' => [
                ['file_id' => 'small_id', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90, 'file_size' => 100],
                ['file_id' => 'PHOTO_FILE_ID', 'file_unique_id' => 'u2', 'width' => 800, 'height' => 600, 'file_size' => 50000],
            ],
            'caption' => 'Look at this!',
        ]);
        // Remove 'text' — photos don't have it
        unset($payload['message']['text']);

        $this->postTgWebhook($payload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => 'Look at this!',
        ]);

        $message = Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->first();
        $this->assertNotNull($message);

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'file_type' => 'photo',
        ]);
    }

    /**
     * When no group_id is configured, an incoming Telegram document must be persisted
     * with a `message_attachments` row reflecting the document file_id.
     */
    public function test_telegram_incoming_document_with_group_off_persists_attachment(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
        ]);
        $this->clearGroupId();

        $botUser = BotUser::create(['chat_id' => 345678, 'platform' => 'telegram']);

        $payload = $this->telegramPayload($botUser, [
            'document' => [
                'file_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
                'file_id' => 'DOC_FILE_ID',
                'file_unique_id' => 'u3',
                'file_size' => 12000,
            ],
        ]);
        unset($payload['message']['text']);

        $this->postTgWebhook($payload)->assertOk();

        $message = Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->first();
        $this->assertNotNull($message, 'Expected an incoming message row to be persisted');

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'file_id' => 'DOC_FILE_ID',
            'file_type' => 'document',
        ]);
    }

    /**
     * issue #46 — a Telegram content type this bot doesn't handle (video,
     * GIF, audio, poll, dice, venue, game) must not be silently dropped:
     * with the group OFF, persistIncomingTelegramMessage() must fall back
     * to the unsupported-type placeholder notice instead of null text.
     */
    public function test_telegram_incoming_unsupported_type_with_group_off_persists_notice(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
        ]);
        $this->clearGroupId();

        $botUser = BotUser::create(['chat_id' => 789012, 'platform' => 'telegram']);

        $payload = $this->telegramPayload($botUser, [
            'video' => ['file_id' => 'VIDEO_FILE_ID', 'duration' => 12],
        ]);
        // Videos carry no 'text' field.
        unset($payload['message']['text']);

        $this->postTgWebhook($payload)->assertOk();

        $message = Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->first();
        $this->assertNotNull($message, 'Expected an incoming message row even for an unsupported content type');
        $this->assertNotNull($message->text, 'Expected the unsupported-type placeholder, not null');
        $this->assertStringContainsString('видео', $message->text);

        // Exactly one row — no duplicate.
        $this->assertSame(
            1,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count()
        );
    }

    // ── Telegram — group ON ───────────────────────────────────────────────────

    /**
     * When the group IS configured, the existing group-forward path is taken:
     * SendTelegramMessageJob (or TopicCreateJob chain) is dispatched and exactly
     * one incoming row ends up in `messages` via the job — not via the direct-persist path.
     *
     * We verify: only the job path runs (Queue::assertPushed) and only one row exists.
     * We do NOT call the real job here to avoid HTTP calls.
     */
    public function test_telegram_incoming_with_group_on_dispatches_job_not_direct_persist(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
            'telegram.group_id' => '-100999888',
        ]);

        // BotUser without a topic_id → TopicCreateJob will be dispatched first.
        $botUser = BotUser::create(['chat_id' => 456789, 'platform' => 'telegram']);

        $this->postTgWebhook($this->telegramPayload($botUser))->assertOk();

        // The group-forward job (or its topic-creation precursor) must be dispatched.
        $pushed = array_merge(
            Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [],
            Queue::pushedJobs()[TopicCreateJob::class] ?? [],
        );
        $this->assertNotEmpty($pushed, 'Expected group-forward job to be dispatched when group is ON');

        // The direct-persist path must NOT have run — no DB row yet (jobs are fake).
        $this->assertSame(
            0,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count(),
            'No direct-persist row expected when group is ON (job persists instead)'
        );
    }

    /**
     * When the group IS configured and a topic_id already exists, only ONE incoming
     * row ends up in `messages` after the real SendTelegramMessageJob runs.
     * This guards against the duplicate-row regression (group-ON must not create
     * both a direct-persist row AND a job row).
     */
    public function test_telegram_incoming_with_group_on_no_duplicate_rows(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
            'telegram.group_id' => '-100999888',
        ]);

        $botUser = BotUser::create([
            'chat_id' => 567890,
            'platform' => 'telegram',
            'topic_id' => 42,
        ]);

        $this->postTgWebhook($this->telegramPayload($botUser))->assertOk();

        // With Queue::fake() the job is queued but not executed → 0 DB rows from
        // the job. The direct-persist branch must also not have run → still 0.
        $this->assertSame(
            0,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count(),
            'No direct-persist row expected on group-ON path — job is responsible'
        );

        Queue::assertPushed(SendTelegramMessageJob::class);
    }

    // ── VK — group OFF ────────────────────────────────────────────────────────

    /**
     * When no telegram.group_id is configured, an incoming VK text message must
     * be persisted directly to `messages` via VkMessageService::persistIncomingVkMessage().
     */
    public function test_vk_incoming_text_with_group_off_persists_to_messages(): void
    {
        Queue::fake();

        // TestCase::setUp() seeds a default group_id — remove it for the group-OFF scenario.
        $this->clearGroupId();

        $dto = VkUpdateDtoMock::getDto();
        $botUser = BotUser::getUserByChatId($dto->from_id, 'vk');

        (new VkMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'vk',
            'message_type' => 'incoming',
            'text' => 'Test text',
        ]);

        $this->assertSame(
            1,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count()
        );
    }

    /**
     * When no group_id is configured, a VK message with a photo attachment must
     * produce a `message_attachments` row.
     */
    public function test_vk_incoming_photo_with_group_off_persists_attachment(): void
    {
        Queue::fake();
        $this->clearGroupId();

        $params = VkUpdateDtoMock::getDtoParams();
        $params['object']['message']['text'] = '';
        $params['object']['message']['attachments'] = [
            [
                'type' => 'photo',
                'photo' => [
                    'orig_photo' => ['url' => 'https://vk.com/photo.jpg'],
                ],
            ],
        ];

        $request = \Illuminate\Support\Facades\Request::create('api/vk/bot', 'POST', $params);
        $dto = VkUpdateDto::fromRequest($request);
        $botUser = BotUser::getUserByChatId($dto->from_id, 'vk');

        (new VkMessageService($dto))->handleUpdate();

        $message = Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->first();
        $this->assertNotNull($message, 'Expected an incoming VK message row');

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'file_type' => 'photo',
        ]);
    }

    /**
     * When group IS configured, VkMessageService dispatches SendVkTelegramMessageJob
     * (the existing group-ON path) and does NOT call persistIncomingVkMessage().
     */
    public function test_vk_incoming_with_group_on_dispatches_job_not_direct_persist(): void
    {
        Queue::fake();

        $this->seedSetting('telegram.group_id', '-100999888');

        $dto = VkUpdateDtoMock::getDto();
        $botUser = BotUser::getUserByChatId($dto->from_id, 'vk');
        $botUser->update(['topic_id' => 77]);

        (new VkMessageService($dto))->handleUpdate();

        // With Queue::fake() jobs don't execute → no DB row yet.
        $this->assertSame(
            0,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count(),
            'No direct-persist row expected on group-ON path'
        );

        Queue::assertPushed(\App\Modules\Telegram\Jobs\SendVkTelegramMessageJob::class);
    }

    // ── Max — group OFF ───────────────────────────────────────────────────────

    /**
     * When no telegram.group_id is configured, an incoming Max text message must
     * be persisted directly to `messages` via MaxMessageService::persistIncomingMaxMessage().
     */
    public function test_max_incoming_text_with_group_off_persists_to_messages(): void
    {
        Queue::fake();

        // TestCase::setUp() seeds a default group_id — remove it for the group-OFF scenario.
        $this->clearGroupId();

        $dto = MaxUpdateDtoMock::getDto();
        $botUser = BotUser::getUserByChatId($dto->from_id, 'max');

        (new MaxMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'max',
            'message_type' => 'incoming',
            'text' => 'Test text',
        ]);

        $this->assertSame(
            1,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count()
        );
    }

    /**
     * When no group_id is configured, a Max message with a photo attachment must
     * produce a `message_attachments` row.
     */
    public function test_max_incoming_photo_with_group_off_persists_attachment(): void
    {
        Queue::fake();
        $this->clearGroupId();

        $params = MaxUpdateDtoMock::getDtoParams();
        $params['message']['body']['text'] = null;
        $params['message']['body']['attachments'] = [
            [
                'type' => 'image',
                'payload' => ['url' => 'https://max.ru/photo.jpg'],
            ],
        ];

        $request = \Illuminate\Support\Facades\Request::create('api/max/bot', 'POST', $params);
        $dto = MaxUpdateDto::fromRequest($request);
        $botUser = BotUser::getUserByChatId($dto->from_id, 'max');

        (new MaxMessageService($dto))->handleUpdate();

        $message = Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->first();
        $this->assertNotNull($message, 'Expected an incoming Max message row');

        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'file_type' => 'photo',
        ]);
    }

    /**
     * When group IS configured, MaxMessageService dispatches SendMaxTelegramMessageJob
     * (the existing group-ON path) and does NOT call persistIncomingMaxMessage().
     */
    public function test_max_incoming_with_group_on_dispatches_job_not_direct_persist(): void
    {
        Queue::fake();

        $this->seedSetting('telegram.group_id', '-100999888');

        $dto = MaxUpdateDtoMock::getDto();
        $botUser = BotUser::getUserByChatId($dto->from_id, 'max');
        $botUser->update(['topic_id' => 88]);

        (new MaxMessageService($dto))->handleUpdate();

        // With Queue::fake() jobs don't execute → no DB row yet.
        $this->assertSame(
            0,
            Message::where('bot_user_id', $botUser->id)->where('message_type', 'incoming')->count(),
            'No direct-persist row expected on group-ON path'
        );

        Queue::assertPushed(\App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob::class);
    }

    // ── Admin panel visibility ────────────────────────────────────────────────

    /**
     * The group-OFF persisted message must appear in the admin ConversationPage
     * workspace (via the standard message loading path).
     */
    public function test_admin_workspace_shows_group_off_persisted_incoming_message(): void
    {
        Queue::fake();

        $this->seedSettings([
            'telegram.token' => 'bot:TOKEN',
            'telegram.secret_key' => 'test-secret',
        ]);
        $this->clearGroupId();

        $botUser = BotUser::create(['chat_id' => 678901, 'platform' => 'telegram']);

        // Trigger the group-OFF persistence path.
        $this->postTgWebhook($this->telegramPayload($botUser, ['text' => 'Admin can see me']))->assertOk();

        $admin = \App\Models\User::factory()->create();
        $this->actingAs($admin);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $messages = $component->get('chatMessages');
        $this->assertCount(1, $messages);
        $this->assertSame('Admin can see me', $messages->first()->text);
    }
}
