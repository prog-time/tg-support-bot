<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendOkTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SendOkTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        app(SettingsService::class)->set('telegram.group_id', '-100123');
        $this->botUser = BotUser::getUserByChatId('chat:C1', 'ok');
    }

    private function job(?OkAttachmentDto $attachment = null): SendOkTelegramMessageJob
    {
        $attachment ??= new OkAttachmentDto('sendMessage', 'Привет');

        return new SendOkTelegramMessageJob(
            $this->botUser->id,
            new OkUpdateDto('mid.1', OkUpdateDto::TYPE_MESSAGE, 'chat:C1', 'user:5', null, null, [], null, []),
            TGTextMessageDto::from([
                'methodQuery' => $attachment->method,
                'chat_id' => '-100123',
                'text' => $attachment->url === null ? $attachment->text : null,
                'parse_mode' => null,
            ]),
            $attachment,
        );
    }

    private function photo(): OkAttachmentDto
    {
        return new OkAttachmentDto('sendPhoto', '🖼 Изображение: https://cdn.test/1', 'https://cdn.test/1', 'photo');
    }

    public function test_photo_is_uploaded_to_telegram_and_stored_for_admin_panel(): void
    {
        Storage::fake('local');
        $this->botUser->update(['topic_id' => 77]);
        Http::fake([
            'https://cdn.test/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 901]]),
            '*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $this->job($this->photo())->handle();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendPhoto') && $request->isMultipart());
        $message = Message::where('to_id', 901)->firstOrFail();
        $this->assertNull($message->text);
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('photo', $attachment->file_type);
        Storage::disk('local')->assertExists($attachment->file_id);
    }

    public function test_file_that_cannot_be_downloaded_is_sent_as_a_link(): void
    {
        $this->botUser->update(['topic_id' => 77]);
        Http::fake([
            'https://cdn.test/*' => Http::response('', 404),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 902]]),
            '*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $this->job($this->photo())->handle();

        $this->assertDatabaseHas('messages', ['to_id' => 902, 'text' => '🖼 Изображение: https://cdn.test/1']);
    }

    public function test_file_rejected_by_telegram_is_sent_as_a_link(): void
    {
        Storage::fake('local');
        $this->botUser->update(['topic_id' => 77]);
        Http::fake([
            'https://cdn.test/*' => Http::response('bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*/sendPhoto' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'PHOTO_INVALID_DIMENSIONS'], 400),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 903]]),
            '*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $this->job($this->photo())->handle();

        $this->assertDatabaseHas('messages', ['to_id' => 903, 'text' => '🖼 Изображение: https://cdn.test/1']);
    }

    public function test_saves_the_message_after_telegram_accepted_it(): void
    {
        $this->botUser->update(['topic_id' => 77]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 900]])]);

        $this->job()->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'ok',
            'message_type' => 'incoming',
            'from_id' => 5,
            'to_id' => 900,
            'text' => 'Привет',
        ]);
    }

    public function test_telegram_failure_saves_nothing(): void
    {
        $this->botUser->update(['topic_id' => 77]);
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'], 400)]);

        $this->job()->handle();

        $this->assertDatabaseMissing('messages', ['bot_user_id' => $this->botUser->id]);
    }

    public function test_missing_topic_creates_it_first(): void
    {
        Http::fake();

        $this->job()->handle();

        Bus::assertDispatched(TopicCreateJob::class);
        $this->assertDatabaseMissing('messages', ['bot_user_id' => $this->botUser->id]);
    }
}
