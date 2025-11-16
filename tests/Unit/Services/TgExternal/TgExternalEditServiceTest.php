<?php

namespace Tests\Unit\Services\TgExternal;

use App\Models\BotUser;
use App\Models\ExternalMessage;
use App\Models\Message;
use App\Services\External\ExternalMessageService;
use App\Services\TgExternal\TgExternalEditService;
use App\Services\TgExternal\TgExternalMessageService;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgExternalEditServiceTest extends TestCase
{
    public function test_edit_text_message(): void
    {
        Message::truncate();
        ExternalMessage::truncate();

        // новое сообщение
        $newMessageText = 'Сообщение от клиента';

        $newMessageParamsDto = ExternalMessageDtoMock::getDtoParams();
        $newMessageParamsDto['text'] = $newMessageText;

        $newMessageDto = ExternalMessageDtoMock::getDto($newMessageParamsDto);

        $botUser = (new BotUser())->getExternalBotUser($newMessageDto);

        (new ExternalMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereMessageParams = [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => $botUser->platform,
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);

        $newMessageData = Message::where($whereMessageParams)
            ->orderBy('id', 'desc')
            ->with('externalMessage')
            ->first();

        $this->assertNotNull($newMessageData->externalMessage);
        // ---------------

        // новое сообщение из группы
        $newGroupMessageDto = TelegramUpdateDto_GroupMock::getDtoParams();
        $newGroupMessageDto['message']['message_thread_id'] = $botUser->topic_id;
        (new TgExternalMessageService(TelegramUpdateDto_GroupMock::getDto($newGroupMessageDto)))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereMessageParams = [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'platform' => $botUser->platform,
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);
        // ---------------

        // изменение сообщения
        $newGroupMessageData = Message::where($whereMessageParams)->orderBy('id', 'desc')->first();

        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDto_GroupMock::getDtoParams()['message'],
        ];

        $editPayload['edited_message']['text'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newGroupMessageData->from_id;
        $editPayload['edited_message']['message_thread_id'] = $botUser->topic_id;

        $editMessageDto = TelegramUpdateDtoMock::getDto($editPayload);

        (new TgExternalEditService($editMessageDto))->handleUpdate();
    }
}
