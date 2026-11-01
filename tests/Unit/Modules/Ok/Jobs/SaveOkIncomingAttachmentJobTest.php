<?php

namespace Tests\Unit\Modules\Ok\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Jobs\SaveOkIncomingAttachmentJob;
use App\Modules\Ok\Services\OkAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaveOkIncomingAttachmentJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(OkAttachmentDto $attachment): Message
    {
        $botUser = BotUser::create(['chat_id' => 'chat:C1', 'platform' => 'ok']);
        $update = new OkUpdateDto('mid.1', OkUpdateDto::TYPE_MESSAGE, 'chat:C1', 'user:5', null, null, [$attachment], null, []);

        (new SaveOkIncomingAttachmentJob($botUser->id, $update, $attachment))->handle(new OkAttachmentStorage());

        return Message::where('bot_user_id', $botUser->id)->firstOrFail();
    }

    public function test_file_is_stored_as_a_message_attachment(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response('bytes', 200, ['Content-Type' => 'image/jpeg'])]);

        $message = $this->runJob(new OkAttachmentDto('sendPhoto', 'link', 'https://cdn.test/1', 'photo'));

        $this->assertNull($message->text);
        $this->assertSame(5, (int) $message->from_id);
        $this->assertSame('photo', $message->attachments()->firstOrFail()->file_type);
    }

    public function test_undownloadable_file_is_saved_as_its_link(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $message = $this->runJob(new OkAttachmentDto('sendPhoto', 'link', 'https://cdn.test/1', 'photo'));

        $this->assertSame('link', $message->text);
        $this->assertSame(0, $message->attachments()->count());
    }

    public function test_text_attachment_is_saved_as_text(): void
    {
        Http::fake();

        $this->assertSame('📍 Геопозиция', $this->runJob(new OkAttachmentDto('sendMessage', '📍 Геопозиция'))->text);

        Http::assertNothingSent();
    }
}
