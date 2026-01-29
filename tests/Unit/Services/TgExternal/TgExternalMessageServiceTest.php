<?php

namespace Tests\Unit\Services\TgExternal;

use App\DTOs\External\ExternalMessageDto;
use App\Jobs\SendWebhookMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgExternal\TgExternalMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_ExternalMock;
use Tests\TestCase;

class TgExternalMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $source;

    private int $external_id;

    private string $url;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();
        BotUser::truncate();

        $this->source = 'live_chat';
        $this->external_id = time();
        $this->url = 'http://test.ru';

        $this->botUser = (new BotUser())->getOrCreateExternalBotUser(ExternalMessageDto::from([
            'source' => $this->source,
            'external_id' => $this->external_id,
            'message_id' => time(),
            'text' => 'Тестовое сообщение',
        ]));
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        Artisan::call('app:generate-token', [
            'source' => $this->source,
            'hook_url' => $this->url,
        ]);
    }

    public function test_send_text_message(): void
    {
        $dtoParams = TelegramUpdateDto_ExternalMock::getDtoParams($this->botUser);
        $dto = TelegramUpdateDto_ExternalMock::getDto($dtoParams);

        (new TgExternalMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendWebhookMessage::class];
        $this->assertEquals(count($pushed), 1);

        $jobData = $pushed[0]['job'];
        $this->assertEquals($this->external_id, $jobData->payload['externalId']);
        $this->assertEquals('send_message', $jobData->payload['type_query']);
    }

    public function test_send_text_message_with_buttons(): void
    {
        $dtoParams = TelegramUpdateDto_ExternalMock::getDtoParams($this->botUser);
        $dtoParams['message']['text'] = "Выберите:\n[[Да|callback:yes]] [[Нет|callback:no]]";

        $dto = TelegramUpdateDto_ExternalMock::getDto($dtoParams);

        (new TgExternalMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendWebhookMessage::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];
        $this->assertEquals($this->external_id, $jobData->payload['externalId']);
        $this->assertEquals('send_message', $jobData->payload['type_query']);

        $this->assertEquals('Выберите:', $jobData->payload['message']['text']);
        $this->assertArrayHasKey('buttons', $jobData->payload['message']);
        $this->assertCount(2, $jobData->payload['message']['buttons']);

        $this->assertEquals('Да', $jobData->payload['message']['buttons'][0]['text']);
        $this->assertEquals('callback', $jobData->payload['message']['buttons'][0]['type']);
        $this->assertEquals('yes', $jobData->payload['message']['buttons'][0]['value']);
    }

    public function test_send_text_message_without_buttons(): void
    {
        $dtoParams = TelegramUpdateDto_ExternalMock::getDtoParams($this->botUser);
        $dtoParams['message']['text'] = 'Простое сообщение без кнопок';

        $dto = TelegramUpdateDto_ExternalMock::getDto($dtoParams);

        (new TgExternalMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendWebhookMessage::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];
        $this->assertEquals('Простое сообщение без кнопок', $jobData->payload['message']['text']);
        $this->assertArrayNotHasKey('buttons', $jobData->payload['message']);
    }
}
