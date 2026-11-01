<?php

namespace Tests\Unit\Modules\Ok\Services;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Jobs\SaveOkIncomingAttachmentJob;
use App\Modules\Ok\Services\OkMessageService;
use App\Modules\Telegram\Jobs\SendOkTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OkMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, OkAttachmentDto> $attachments
     */
    private function update(?string $text, array $attachments = [], ?string $name = null): OkUpdateDto
    {
        return new OkUpdateDto(
            event_id: 'mid.1',
            type: OkUpdateDto::TYPE_MESSAGE,
            chatId: 'chat:C1',
            senderId: 'user:5',
            senderName: $name,
            text: $text,
            attachments: $attachments,
            systemType: null,
            rawData: [],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function photo(): OkAttachmentDto
    {
        return new OkAttachmentDto('sendPhoto', '🖼 Изображение: https://cdn.test/1', 'https://cdn.test/1', 'photo');
    }

    public function test_single_message_is_dispatched_as_one_job(): void
    {
        (new OkMessageService($this->update('Текст')))->handleUpdate();

        Queue::assertPushed(SendOkTelegramMessageJob::class, fn ($job) => $job->queryParams->text === 'Текст'
            && $job->queryParams->parse_mode === null);
    }

    public function test_text_and_attachments_keep_order_after_creating_one_topic(): void
    {
        Bus::fake();

        (new OkMessageService($this->update('Текст', [$this->photo()])))->handleUpdate();

        Bus::assertChained([
            TopicCreateJob::class,
            fn (SendOkTelegramMessageJob $job) => $job->attachment->text === 'Текст',
            fn (SendOkTelegramMessageJob $job) => $job->attachment->method === 'sendPhoto' && $job->queryParams->text === null,
        ]);
    }

    public function test_attachments_are_saved_without_group(): void
    {
        app(SettingsService::class)->forget('telegram.group_id');

        (new OkMessageService($this->update('Текст', [$this->photo()])))->handleUpdate();

        $this->assertDatabaseHas('messages', ['platform' => 'ok', 'text' => 'Текст']);
        Queue::assertPushed(SaveOkIncomingAttachmentJob::class, 1);
    }

    public function test_existing_display_name_is_not_overwritten(): void
    {
        BotUser::getUserByChatId('chat:C1', 'ok')->update(['display_name' => 'Старое имя']);

        (new OkMessageService($this->update('Текст', [], 'Новое имя')))->handleUpdate();

        $this->assertDatabaseHas('bot_users', ['chat_id' => 'chat:C1', 'display_name' => 'Старое имя']);
    }

    public function test_auto_reply_mode_dispatches_ai_reply(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('ai.enabled', true);
        $settings->set('ai.auto_reply', true);

        (new OkMessageService($this->update('Вопрос')))->handleUpdate();

        Queue::assertPushed(SendAiReplyJob::class);
    }

    public function test_attachment_only_message_does_not_start_ai(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('ai.enabled', true);
        $settings->set('ai.auto_reply', true);

        (new OkMessageService($this->update(null, [$this->photo()])))->handleUpdate();

        Queue::assertNotPushed(SendAiReplyJob::class);
    }
}
