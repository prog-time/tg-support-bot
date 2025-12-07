<?php

namespace Tests\Unit\Services\TgExternal;

use App\Jobs\SendWebhookMessage;
use App\Models\BotUser;
use App\Models\ExternalMessage;
use App\Models\Message;
use App\Services\TgExternal\TgExternalEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDto_ExternalMock;
use Tests\TestCase;

class TgExternalEditServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        ExternalMessage::truncate();
        Message::truncate();

        Artisan::call('app:generate-token', [
            'source' => config('testing.external.source'),
            'hook_url' => config('testing.external.hook_url'),
        ]);
    }

    public function test_edit_text_message(): void
    {
        $messageDto = ExternalMessageDtoMock::getDto();
        $this->botUser = (new BotUser())->getExternalBotUser($messageDto);

        $this->botUser->topic_id = time();
        $this->botUser->save();

        $newGroupMessageData = Message::create([
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => $this->botUser->platform,
            'from_id' => time(),
            'to_id' => time(),
        ]);

        $newGroupMessageData->externalMessage()->create([
            'text' => 'Тестовое сообщение',
            'file_id' => null,
            'file_type' => null,
        ]);

        // Изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDto_ExternalMock::getDtoParams()['message'],
        ];

        $editTextMessage = 'Новый текст сообщения';
        $editPayload['edited_message']['text'] = $editTextMessage;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;
        $editPayload['edited_message']['message_id'] = $newGroupMessageData->from_id;

        $editMessageDto = TelegramUpdateDto_ExternalMock::getDto($editPayload);

        (new TgExternalEditService($editMessageDto))->handleUpdate();

        // Проверка джоб (если пушатся)
        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendWebhookMessage::class] ?? [];
        $this->assertNotEmpty($pushed);

        // Проверка редактирования джобы
        $jobData = array_pop($pushed)['job'];

        $this->assertEquals($editTextMessage, $jobData->payload['message']['text']);
        $this->assertEquals($this->botUser->externalUser->external_id, $jobData->payload['externalId']);
    }
}
