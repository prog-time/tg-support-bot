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
        Queue::fake();
        Message::truncate();

        $botUser = (new BotUser())->getExternalBotUser(ExternalMessageDto::from([
            'source' => config('testing.external.source'),
            'external_id' => config('testing.external.external_id'),
            'message_id' => time(),
            'text' => 'Тестовое сообщение',
        ]));

        $dtoParams = TelegramUpdateDto_ExternalMock::getDtoParams($botUser);
        $dto = TelegramUpdateDto_ExternalMock::getDto($dtoParams);

        (new TgExternalMessageService($dto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->externalUser->source,
            'message_type' => 'outgoing',
        ]);

        $message = Message::where('bot_user_id', $botUser->id)->first();

        // Проверяем, что джоб был поставлен в очередь
        Queue::assertPushed(SendWebhookMessage::class, function ($job) use ($message) {
            return
                $job->payload['externalId'] === $this->external_id &&
                $job->payload['type_query'] === 'send_message' &&
                $job->payload['message']['to_id'] === $message->to_id &&
                $job->payload['message']['from_id'] === $message->from_id;
        });
    }
}
