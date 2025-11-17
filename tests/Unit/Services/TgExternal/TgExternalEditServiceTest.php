<?php

namespace Tests\Unit\Services\TgExternal;

use App\Jobs\SendWebhookMessage;
use App\Models\BotUser;
use App\Models\ExternalMessage;
use App\Models\Message;
use App\Services\TgExternal\TgExternalEditService;
use App\Services\TgExternal\TgExternalMessageService;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_ExternalMock;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\TestCase;

class TgExternalEditServiceTest extends TestCase
{
    private BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        ExternalMessage::truncate();
        Message::truncate();
    }

    public function test_edit_text_message(): void
    {
        // Новое сообщение из группы
        $newGroupMessageDto = TelegramUpdateDto_ExternalMock::getDtoParams();
        $groupMessageDto = TelegramUpdateDto_GroupMock::getDto($newGroupMessageDto);

        $this->botUser = (new BotUser())->getTelegramUserData($groupMessageDto);

        (new TgExternalMessageService($groupMessageDto))->handleUpdate();

        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => $this->botUser->platform,
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);

        $newGroupMessageData = Message::where($whereMessageParams)
            ->orderBy('id', 'desc')
            ->first();

        // Изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDto_ExternalMock::getDtoParams()['message'],
        ];

        $editTextMessage = 'Новый текст сообщения';
        $editPayload['edited_message']['text'] = $editTextMessage;
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
