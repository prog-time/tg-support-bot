<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\External\ExternalMessageAnswerDtoMock;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    private int $externalId;

    public function setUp(): void
    {
        parent::setUp();

        $this->externalId = time();
    }

    public function testWebhookService(): void
    {
        $url = 'https://node.tg-support-bot.ru/push-message';

        Http::fake([
            $url => Http::response('{"status":"ok"}', 200),
        ]);

        $saveMessageData = ExternalMessageAnswerDtoMock::getDto();

        $dataMessage = [
            'type_query' => 'send_message',
            'externalId' => $this->externalId,
            'message' => $saveMessageData->result->toArray(),
        ];

        $result = (new WebhookService())->sendMessage($url, $dataMessage);

        $this->assertNotEmpty($result);
        $this->assertSame('{"status":"ok"}', $result);

        Http::assertSent(function ($request) use ($url, $dataMessage) {
            return $request->url() === $url
                && $request->method() === 'POST'
                && $request->data() === $dataMessage;
        });
    }

    public function testWebhookServiceReturnsNullOnFailure(): void
    {
        $url = 'https://node.tg-support-bot.ru/push-message';

        Http::fake([
            $url => Http::response('Server error', 500),
        ]);

        $result = (new WebhookService())->sendMessage($url, ['type_query' => 'send_message']);

        $this->assertNull($result);
    }
}
