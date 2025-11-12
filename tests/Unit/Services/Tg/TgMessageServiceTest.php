<?php

namespace Tests\Unit\Services\Tg;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\Tg\TgMessageService;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgMessageServiceTest extends TestCase
{
    public function sendTestQuery(TelegramUpdateDto $dto): Message
    {
        $botUser = BotUser::getTelegramUserData($dto);

        (new TgMessageService($dto))->handleUpdate();

        $this->app->make('queue')->connection('sync');

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
        ]);

        return Message::where('bot_user_id', $botUser->id)->first();
    }

    public function test_send_text_message(): void
    {
        Message::truncate();

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto());
    }

    public function test_send_photo(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['photo'] = [
            [
                'file_id' => config('testing.tg_file.photo'),
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_document(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['document'] = [
            'file_id' => config('testing.tg_file.document'),
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_sticker(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['sticker'] = [
            'file_id' => config('testing.tg_file.sticker'),
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_location(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['location'] = [
            'latitude' => 55.728387,
            'longitude' => 37.611953,
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_video_note(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['video_note'] = [
            'file_id' => config('testing.tg_file.video_note'),
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_voice(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['voice'] = [
            'file_id' => config('testing.tg_file.voice'),
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }

    public function test_send_contact(): void
    {
        Message::truncate();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['contact'] = [
            'phone_number' => '79999999999',
            'first_name' => 'Тестовый',
            'last_name' => 'Тест',
            'user_id' => config('testing.tg_private.chat_id'),
        ];

        $this->sendTestQuery(TelegramUpdateDtoMock::getDto($payload));
    }
}
