<?php

namespace Tests\Unit\Services\Tg;

use App\Models\BotUser;
use App\Models\Message;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgEditMessageServiceTest extends TestCase
{
    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
    }

    public function test_edit_text_message(): void
    {
        Message::truncate();

        // новое сообщение
        $newMessageDto = TelegramUpdateDtoMock::getDto();
        (new TgMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);

        $newMessageData = Message::where($whereMessageParams)->orderBy('id', 'desc')->first();
        // ---------------

        // изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDtoMock::getDtoParams()['message'],
        ];

        $editPayload['edited_message']['text'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['chat']['id'] = $this->botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $editMessageDto = TelegramUpdateDtoMock::getDto($editPayload);
        (new TgEditMessageService($editMessageDto))->handleUpdate();
    }

    public function test_edit_caption_message(): void
    {
        Message::truncate();

        $photoData = [
            [
                'file_id' => config('testing.tg_file.photo'),
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        // новое сообщение
        $newMessagePayload = TelegramUpdateDtoMock::getDtoParams();
        unset($newMessagePayload['message']['text']);

        $newMessagePayload['message']['photo'] = $photoData;
        $newMessagePayload['message']['caption'] = 'Версия 1';

        $newMessageDto = TelegramUpdateDtoMock::getDto($newMessagePayload);
        (new TgMessageService($newMessageDto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
        ];
        $this->assertDatabaseHas('messages', $whereMessageParams);

        $newMessageData = Message::where($whereMessageParams)->orderBy('id', 'desc')->first();
        // ---------------

        // изменение сообщения
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDtoMock::getDtoParams()['message'],
        ];
        unset($editPayload['edited_message']['text']);

        $editPayload['edited_message']['photo'] = $photoData;

        $editPayload['edited_message']['caption'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['chat']['id'] = $this->botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $editMessageDto = TelegramUpdateDtoMock::getDto($editPayload);
        (new TgEditMessageService($editMessageDto))->handleUpdate();
    }
}
