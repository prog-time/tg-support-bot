<?php

namespace Tests\Unit\Modules\Ok\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ok\Api\OkMethods;
use App\Modules\Ok\DTOs\OkAnswerDto;
use App\Modules\Ok\DTOs\OkFileMessageDto;
use App\Modules\Ok\Jobs\SendOkFileMessageJob;
use App\Modules\Ok\Services\OkAttachmentStorage;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SendOkFileMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = (string) tempnam(sys_get_temp_dir(), 'ok');
        file_put_contents($this->path, 'bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function job(string $mime, string $text = 'Подпись', ?int $messageId = null): SendOkFileMessageJob
    {
        return new SendOkFileMessageJob(OkFileMessageDto::from([
            'chat_id' => 'chat:C1',
            'text' => $text,
            'mime' => $mime,
            'name' => 'f',
            'path' => $this->path,
            'messageId' => $messageId,
        ]));
    }

    public function test_uploads_and_sends_the_file_then_removes_the_temp_copy(): void
    {
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->with('bytes', 'IMAGE', 'f', 'image/jpeg')->andReturn('tok');
        $ok->shouldReceive('sendAttachment')->once()->with('chat:C1', 'Подпись', 'IMAGE', 'tok')->andReturn([]);

        $this->job('image/jpeg')->handle($ok, new OkAttachmentStorage());

        $this->assertFileDoesNotExist($this->path);
    }

    public function test_rejected_video_falls_back_to_a_plain_file(): void
    {
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->with('bytes', 'VIDEO', 'f', 'video/mp4')->andThrow(new \RuntimeException('415', 1));
        $ok->shouldReceive('uploadFile')->once()->with('bytes', 'FILE', 'f', 'video/mp4')->andReturn('tok');
        $ok->shouldReceive('sendAttachment')->once()->with('chat:C1', 'Подпись', 'FILE', 'tok')->andReturn([]);

        $this->job('video/mp4')->handle($ok, new OkAttachmentStorage());
    }

    public function test_when_the_file_fails_the_text_is_still_sent(): void
    {
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->andThrow(new \RuntimeException('fail', 1));
        $ok->shouldReceive('sendQuery')->once()->with('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'Подпись'])->andReturn(OkAnswerDto::fromData([]));

        $this->job('application/pdf')->handle($ok, new OkAttachmentStorage());
    }

    public function test_file_without_text_that_fails_sends_nothing(): void
    {
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->andThrow(new \RuntimeException('fail', 1));
        $ok->shouldNotReceive('sendQuery');

        $this->job('application/pdf', '')->handle($ok, new OkAttachmentStorage());
    }

    public function test_keeps_a_local_copy_on_the_message(): void
    {
        Storage::fake('local');
        $botUser = BotUser::create(['chat_id' => 'chat:C1', 'platform' => 'ok']);
        $message = Message::create(['bot_user_id' => $botUser->id, 'platform' => 'ok', 'message_type' => 'outgoing', 'from_id' => 0, 'to_id' => 0]);
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->andReturn('tok');
        $ok->shouldReceive('sendAttachment')->andReturn([]);

        $this->job('image/jpeg', '', $message->id)->handle($ok, new OkAttachmentStorage());

        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('photo', $attachment->file_type);
        Storage::disk('local')->assertExists($attachment->file_id);
    }

    private function telegramJob(): SendOkFileMessageJob
    {
        $job = new SendOkFileMessageJob(OkFileMessageDto::from([
            'chat_id' => 'chat:C1',
            'text' => '',
            'mime' => 'image/jpeg',
            'name' => 'photo.jpg',
            'telegramFileId' => 'file-1',
        ]));
        $job->retryDelaySeconds = 0;

        return $job;
    }

    public function test_telegram_download_is_repeated_after_a_failure(): void
    {
        Http::fake([
            '*/getFile*' => Http::sequence()
                ->push(['ok' => false], 500)
                ->push(['ok' => true, 'result' => ['file_path' => 'photos/1.jpg']]),
            '*/file/bot*' => Http::response('bytes'),
        ]);
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->with('bytes', 'IMAGE', 'photo.jpg', 'image/jpeg')->andReturn('tok');
        $ok->shouldReceive('sendAttachment')->once()->andReturn([]);

        $this->telegramJob()->handle($ok, new OkAttachmentStorage());
    }

    public function test_telegram_download_goes_through_telegram_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://10.0.0.1:1080']);
        $proxies = [];

        Http::fake(function ($request, array $options) use (&$proxies) {
            $proxies[] = $options['proxy'] ?? null;

            return str_contains($request->url(), '/getFile')
                ? Http::response(['ok' => true, 'result' => ['file_path' => 'photos/1.jpg']])
                : Http::response('bytes');
        });
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldReceive('uploadFile')->once()->andReturn('tok');
        $ok->shouldReceive('sendAttachment')->once()->andReturn([]);

        $this->telegramJob()->handle($ok, new OkAttachmentStorage());

        $this->assertSame(['socks5h://10.0.0.1:1080', 'socks5h://10.0.0.1:1080'], $proxies);
    }

    public function test_failed_delivery_is_reported_in_the_topic_and_logged_without_token(): void
    {
        Queue::fake();
        BotUser::create(['chat_id' => 'chat:C1', 'platform' => 'ok', 'topic_id' => 77]);
        Http::fake(fn ($request) => throw new ConnectionException('reset for ' . $request->url()));

        $logged = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning', 'error', 'log')->andReturnUsing(function (...$args) use (&$logged) {
            $logged[] = $args;
        });
        $ok = Mockery::mock(OkMethods::class);
        $ok->shouldNotReceive('uploadFile');

        $this->telegramJob()->handle($ok, new OkAttachmentStorage());

        Queue::assertPushed(SendTelegramSimpleQueryJob::class, fn ($job) => $job->queryParams->message_thread_id === 77
            && str_contains((string) $job->queryParams->text, 'не доставлен'));
        $this->assertStringNotContainsString('getFile', (string) json_encode($logged));
    }
}
