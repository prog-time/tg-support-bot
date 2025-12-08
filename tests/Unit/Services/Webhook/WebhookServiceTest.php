<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookService;
use Tests\Mocks\External\ExternalMessageAnswerDtoMock;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    private string $externalId;

    public function setUp(): void
    {
        parent::setUp();

        $this->externalId = config('testing.external.external_id');
    }

    public function testWebhookService(): void
    {
        $url = 'https://node.tg-support-bot.ru/push-message';

        $saveMessageData = ExternalMessageAnswerDtoMock::getDto();

        $dataMessage = [
            'type_query' => 'send_message',
            'externalId' => $this->externalId,
            'message' => $saveMessageData->result->toArray(),
        ];

        $result = (new WebhookService())->sendMessage($url, $dataMessage);
        $this->assertNotEmpty($result);
    }
}
