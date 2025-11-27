<?php

namespace Tests\Unit\Services\TgExternal;

use App\DTOs\External\ExternalMessageDto;
use App\Jobs\SendWebhookMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgExternal\TgExternalMessageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_ExternalMock;
use Tests\TestCase;

class TgExternalMessageServiceTest extends TestCase
{
    private string $source;

    private string $external_id;

    private string $url;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');
        $this->url = config('testing.external.hook_url');

        Artisan::call('app:generate-token', [
            'source' => $this->source,
            'hook_url' => $this->url,
        ]);
    }

    public function test_send_text_message(): void
    {
        $botUser = (new BotUser())->getExternalBotUser(ExternalMessageDto::from([
            'source' => config('testing.external.source'),
            'external_id' => config('testing.external.external_id'),
            'message_id' => time(),
            'text' => 'Тестовое сообщение',
        ]));

        $dtoParams = TelegramUpdateDto_ExternalMock::getDtoParams($botUser);
        $dto = TelegramUpdateDto_ExternalMock::getDto($dtoParams);

        (new TgExternalMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendWebhookMessage::class];
        $this->assertEquals(count($pushed), 1);

        $jobData = $pushed[0]['job'];
        $this->assertEquals($this->external_id, $jobData->payload['externalId']);
        $this->assertEquals('send_message', $jobData->payload['type_query']);
    }
}
