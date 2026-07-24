<?php

namespace Tests\Unit\Modules\Avito\Api;

use App\Modules\Avito\Api\AvitoMethods;
use App\Modules\Avito\DTOs\AvitoAnswerDto;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsService::class);
        $settings->set('avito.base_url', 'https://api.avito.ru');
        $settings->set('avito.user_id', '555');
        $settings->set('avito.client_id', 'cid');
        $settings->set('avito.client_secret', 'sec');
    }

    public function test_send_message_authenticates_and_posts_to_chat(): void
    {
        Http::fake([
            'api.avito.ru/token' => Http::response([
                'access_token' => 'tok123',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ]),
            'api.avito.ru/messenger/v1/accounts/*/chats/*/messages' => Http::response(['id' => 'm1'], 200),
        ]);

        $answer = (new AvitoMethods())->sendQuery('sendMessage', [
            'chat_id' => 'u2i-abc',
            'text' => 'hi',
        ]);

        $this->assertInstanceOf(AvitoAnswerDto::class, $answer);
        $this->assertSame(200, $answer->response_code);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/token')
            && $req['grant_type'] === 'client_credentials'
            && $req['client_id'] === 'cid');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'messenger/v1/accounts/555/chats/u2i-abc/messages')
            && $req->hasHeader('Authorization', 'Bearer tok123')
            && $req['message']['text'] === 'hi'
            && $req['type'] === 'text');
    }

    public function test_send_query_returns_error_dto_for_unknown_method(): void
    {
        $answer = (new AvitoMethods())->sendQuery('unknownMethod', ['chat_id' => 'c1']);

        $this->assertSame(500, $answer->response_code);
        $this->assertStringContainsString('Unknown method', $answer->error_message ?? '');
    }

    public function test_send_query_returns_error_dto_when_send_message_fails(): void
    {
        Http::fake([
            'api.avito.ru/token' => Http::response(['access_token' => 'tok123', 'expires_in' => 86400]),
            'api.avito.ru/messenger/v1/accounts/*/chats/*/messages' => Http::response('Bad Request', 400),
        ]);

        $answer = (new AvitoMethods())->sendQuery('sendMessage', [
            'chat_id' => 'u2i-abc',
            'text' => 'hi',
        ]);

        $this->assertSame(500, $answer->response_code);
    }
}
