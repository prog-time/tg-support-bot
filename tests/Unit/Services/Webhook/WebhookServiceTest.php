<?php

namespace Tests\Unit\Services\Webhook;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\Services\Webhook\WebhookService;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    private WebhookService $service;

    private string $externalId;

    public function setUp(): void
    {
        parent::setUp();

        $this->externalId = 'EXQFuJE91JY2';

        $this->service = new WebhookService();
    }

    public function testWebhookService(): void
    {
        $url = config('app.url') . ':3001/push-message';

        $saveMessageData = ExternalMessageAnswerDto::from([
            'status' => true,
            'result' => ExternalMessageResponseDto::from([
                'message_type' => 'outgoing',
                'to_id' => time(),
                'from_id' => time(),
                'text' => 'Тестовое сообщение',
                'date' => date('d.m.Y H:i:s'),
                'content_type' => 'text' ,
                'file_id' => null,
                'file_url' => null,
                'file_type' => null,
            ]),
        ]);

        $dataMessage = [
            'type_query' => 'send_message',
            'externalId' => $this->externalId,
            'message' => $saveMessageData->result->toArray(),
        ];

        $this->service->sendMessage($url, $dataMessage);
    }
}
