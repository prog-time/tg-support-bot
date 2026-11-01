<?php

namespace Tests\Unit\Modules\Ok\Api;

use App\Modules\Ok\Api\OkMethods;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OkMethodsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ok-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->set('ok.token', self::TOKEN);
    }

    public function test_send_message_posts_to_the_chat(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message_id' => 'mid.1'])]);

        $answer = (new OkMethods())->sendQuery('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'Привет']);

        $this->assertSame(200, $answer->response_code);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.ok.ru/graph/chat:C1/messages?access_token=' . self::TOKEN)
            && $request->header('Content-Type')[0] === 'application/json;charset=utf-8'
            && $request['recipient'] === ['chat_id' => 'chat:C1']
            && $request['message'] === ['text' => 'Привет']);
    }

    public function test_error_in_a_200_response_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['error_code' => 102, 'error_msg' => 'PARAM_SESSION_EXPIRED'])]);

        $answer = (new OkMethods())->sendQuery('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'x']);

        $this->assertSame(500, $answer->response_code);
        $this->assertStringContainsString('PARAM_SESSION_EXPIRED', (string) $answer->error_message);
    }

    public function test_success_false_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => false])]);

        $answer = (new OkMethods())->sendQuery('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'x']);

        $this->assertSame(500, $answer->response_code);
    }

    public function test_network_error_does_not_expose_the_token(): void
    {
        Http::fake(fn ($request) => throw new ConnectionException('cURL error 28 for ' . $request->url()));

        $logged = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function ($level, $message) use (&$logged) {
            $logged[] = $message;
        });

        $answer = (new OkMethods())->sendQuery('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'x']);

        $this->assertSame(500, $answer->response_code);
        $this->assertStringNotContainsString(self::TOKEN, implode(' ', $logged) . $answer->error_message);
    }

    public function test_subscribe_posts_the_url(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->assertSame(['success' => true], (new OkMethods())->subscribeWebhook('https://app.test/hook'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/graph/me/subscribe?')
            && $request['url'] === 'https://app.test/hook');
    }

    public function test_image_token_comes_from_the_upload_response(): void
    {
        Http::fake([
            'https://api.ok.ru/graph/me/fileUploadUrl*' => Http::response(['url' => 'https://iuup.test/upload', 'file_id' => 'f1']),
            'https://iuup.test/*' => Http::response(['photos' => ['f1' => ['token' => 'image-token']]]),
        ]);

        $this->assertSame('image-token', (new OkMethods())->uploadFile('bytes', 'IMAGE', 'p.jpg', 'image/jpeg'));

        Http::assertSent(fn ($request) => $request->url() === 'https://iuup.test/upload' && $request->isMultipart());
    }

    public function test_file_token_comes_from_the_url_response(): void
    {
        Http::fake([
            'https://api.ok.ru/graph/me/fileUploadUrl*' => Http::response(['url' => 'https://fu.test/upload', 'token' => 'file-token']),
            'https://fu.test/*' => Http::response(''),
        ]);

        $this->assertSame('file-token', (new OkMethods())->uploadFile('bytes', 'FILE', 'a.pdf', 'application/pdf'));
    }

    public function test_audio_is_uploaded_as_a_raw_body_with_range(): void
    {
        Http::fake([
            'https://api.ok.ru/graph/me/fileUploadUrl*' => Http::response(['url' => 'https://vu.test/upload', 'token' => 'audio-token']),
            'https://vu.test/*' => Http::response(''),
        ]);

        $this->assertSame('audio-token', (new OkMethods())->uploadFile('12345', 'AUDIO', 'a.ogg', 'audio/ogg'));

        Http::assertSent(fn ($request) => $request->url() === 'https://vu.test/upload'
            && $request->header('Content-Range')[0] === 'bytes 0-4/5'
            && $request->header('Content-Type')[0] === 'audio/ogg'
            && $request->body() === '12345');
    }

    public function test_upload_failure_throws(): void
    {
        Http::fake([
            'https://api.ok.ru/graph/me/fileUploadUrl*' => Http::response(['url' => 'https://vu.test/upload', 'token' => 't']),
            'https://vu.test/*' => Http::response('', 415),
        ]);

        $this->expectException(\RuntimeException::class);

        (new OkMethods())->uploadFile('x', 'VIDEO', 'v.mp4', 'video/mp4');
    }

    public function test_send_attachment_waits_until_the_file_is_processed(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error_code' => 400, 'error_msg' => 'attachment.not.ready'])
            ->push(['success' => true])]);

        (new OkMethods(0))->sendAttachment('chat:C1', 'Подпись', 'FILE', 'file-token');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['message'] === [
            'text' => 'Подпись',
            'attachment' => ['type' => 'FILE', 'payload' => ['token' => 'file-token']],
        ]);
    }

    public function test_send_attachment_gives_up_after_the_last_attempt(): void
    {
        Http::fake(['*' => Http::response(['error_code' => 400, 'error_msg' => 'bad'])]);

        try {
            (new OkMethods(0))->sendAttachment('chat:C1', '', 'FILE', 'token');
            $this->fail('Expected an exception');
        } catch (\RuntimeException) {
            Http::assertSentCount(4);
        }
    }
}
