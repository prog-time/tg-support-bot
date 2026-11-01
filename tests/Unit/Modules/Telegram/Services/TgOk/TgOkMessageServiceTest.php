<?php

namespace Tests\Unit\Modules\Telegram\Services\TgOk;

use App\Models\BotUser;
use App\Modules\Ok\Jobs\SendOkFileMessageJob;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Services\TgOk\TgOkMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TgOkMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        BotUser::getUserByChatId('chat:C1', 'ok')->update(['topic_id' => 77]);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function update(array $message): TelegramUpdateDto
    {
        return new TelegramUpdateDto(
            updateId: 1,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'supergroup',
            chatId: -100123,
            messageThreadId: 77,
            messageId: 55,
            text: $message['text'] ?? null,
            caption: $message['caption'] ?? null,
            rawData: ['message' => $message],
        );
    }

    public function test_text_is_saved_and_queued_for_the_user(): void
    {
        (new TgOkMessageService($this->update(['text' => 'Ответ'])))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'platform' => 'ok',
            'message_type' => 'outgoing',
            'from_id' => 55,
            'text' => 'Ответ',
        ]);
        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->chat_id === 'chat:C1'
            && $job->queryParams->text === 'Ответ');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function files(): array
    {
        return [
            'photo' => [['photo' => [['file_id' => 'small'], ['file_id' => 'big']]], 'big', 'image/jpeg', 'photo.jpg'],
            'document' => [['document' => ['file_id' => 'd', 'mime_type' => 'application/pdf', 'file_name' => 'a.pdf']], 'd', 'application/pdf', 'a.pdf'],
            'voice' => [['voice' => ['file_id' => 'v', 'mime_type' => 'audio/ogg']], 'v', 'audio/ogg', 'voice.ogg'],
            'audio' => [['audio' => ['file_id' => 'a', 'mime_type' => 'audio/mpeg', 'file_name' => 's.mp3']], 'a', 'audio/mpeg', 's.mp3'],
            'video' => [['video' => ['file_id' => 'w', 'mime_type' => 'video/mp4']], 'w', 'video/mp4', 'video.mp4'],
            'video note' => [['video_note' => ['file_id' => 'n']], 'n', 'video/mp4', 'video.mp4'],
            'sticker' => [['sticker' => ['file_id' => 's', 'emoji' => '😀']], 's', 'image/webp', 'sticker.webp'],
        ];
    }

    /**
     * @param array<string, mixed> $message
     */
    #[DataProvider('files')]
    public function test_every_file_type_is_queued_with_caption(array $message, string $fileId, string $mime, string $name): void
    {
        (new TgOkMessageService($this->update($message + ['caption' => 'Подпись'])))->handleUpdate();

        Queue::assertPushed(SendOkFileMessageJob::class, fn ($job) => $job->file->telegramFileId === $fileId
            && $job->file->mime === $mime
            && $job->file->name === $name
            && $job->file->text === 'Подпись'
            && $job->file->messageId !== null);
    }

    public function test_location_goes_as_a_native_attachment(): void
    {
        (new TgOkMessageService($this->update(['location' => ['latitude' => 55.7, 'longitude' => 37.6]])))->handleUpdate();

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->attachment === [
            'type' => 'LOCATION',
            'payload' => ['latitude' => 55.7, 'longitude' => 37.6],
        ]);
    }

    public function test_contact_goes_as_text(): void
    {
        (new TgOkMessageService($this->update(['contact' => ['first_name' => 'Иван', 'phone_number' => '+7900']])))->handleUpdate();

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => str_contains($job->queryParams->text, 'Телефон: +7900'));
    }

    public function test_message_without_content_is_logged_and_not_sent(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        (new TgOkMessageService($this->update(['poll' => ['id' => '1']])))->handleUpdate();

        Queue::assertNotPushed(SendOkSimpleMessageJob::class);
        Queue::assertNotPushed(SendOkFileMessageJob::class);
    }
}
