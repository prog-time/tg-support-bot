<?php

namespace Tests\Unit\Services\TgVk;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkMessageService;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgVkMessageServiceTest extends TestCase
{
    public function sendTestQuery(TelegramUpdateDto $dto): Message
    {
        $botUser = BotUser::getTelegramUserData($dto);

        (new TgVkMessageService($dto))->handleUpdate();

        $this->app->make('queue')->connection('sync');

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'vk',
        ]);

        return Message::where('bot_user_id', $botUser->id)->first();
    }

    public function test_send_text_message(): void
    {
        Message::truncate();

        $dto = TelegramUpdateDto_VKMock::getDto();

        $this->sendTestQuery($dto);
    }

    public function test_send_photo(): void
    {
        Message::truncate();

        $fileId = config('testing.tg_file.photo');

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['photo'] = [
            [
                'file_id' => $fileId,
            ],
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_document(): void
    {
        Message::truncate();

        $fileId = config('testing.tg_file.document');

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['document'] = [
            'file_id' => $fileId,
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_sticker(): void
    {
        Message::truncate();

        $fileId = config('testing.tg_file.sticker');

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['sticker'] = [
            'emoji' => '👍',
            'file_id' => $fileId,
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_location(): void
    {
        Message::truncate();

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['location'] = [
            'latitude' => 55.728387,
            'longitude' => 37.611953,
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_video_note(): void
    {
        Message::truncate();

        $fileId = config('testing.tg_file.video_note');

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['video_note'] = [
            'file_id' => $fileId,
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_voice(): void
    {
        Message::truncate();

        $fileId = config('testing.tg_file.voice');

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['voice'] = [
            'file_id' => $fileId,
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }

    public function test_send_contact(): void
    {
        Message::truncate();

        $dtoParams = TelegramUpdateDto_VKMock::getDtoParams();
        $dtoParams['message']['contact'] = [
            'phone_number' => '79999999999',
            'first_name' => 'Тестовый',
            'last_name' => 'Тест',
            'user_id' => config('testing.tg_private.chat_id'),
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($dtoParams);
        $this->sendTestQuery($dto);
    }
}
