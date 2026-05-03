<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\External\ExternalMessageAnswerDtoMock;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    private string $url;

    private array $dataMessage;

    public function setUp(): void
    {
        parent::setUp();

        $this->url = 'https://node.tg-support-bot.ru/push-message';

        $saveMessageData = ExternalMessageAnswerDtoMock::getDto();

        $this->dataMessage = [
            'type_query' => 'send_message',
            'externalId' => time(),
            'message' => $saveMessageData->result->toArray(),
        ];
    }

    public function test_send_message_returns_body_on_success(): void
    {
        Http::fake([
            $this->url => Http::response('{"ok":true}', 200),
        ]);

        $result = (new WebhookService())->sendMessage($this->url, $this->dataMessage);

        $this->assertSame('{"ok":true}', $result);

        Http::assertSent(function ($request) {
            return $request->url() === $this->url
                && $request->method() === 'POST'
                && $request->data() === $this->dataMessage;
        });
    }

    public function test_send_message_returns_null_on_failure(): void
    {
        Http::fake([
            $this->url => Http::response('error', 500),
        ]);

        $result = (new WebhookService())->sendMessage($this->url, $this->dataMessage);

        $this->assertNull($result);
    }
}
