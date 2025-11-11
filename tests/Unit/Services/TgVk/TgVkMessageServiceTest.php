<?php

namespace Tests\Unit\Services\TgVk;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class TgVkMessageServiceTest extends TestCase
{
    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $botUser = BotUser::getUserByChatId(config('testing.vk_private.chat_id'), 'vk');

        $this->basicPayload = [
            'update_id' => time(),
            'message' => [
                'message_id' => time(),
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => config('testing.tg_group.chat_id'),
                    'title' => 'Prog-Time | Чаты',
                    'is_forum' => true,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'message_thread_id' => $botUser->topic_id,
                'text' => 'Тестовое сообщение',
            ],
        ];
    }

    public function sendTestQuery(array $payload, bool $statusDelete = true): Message
    {
        $request = Request::create('api/telegram/bot', 'POST', $payload);
        $dto = TelegramUpdateDto::fromRequest($request);

        $botUser = BotUser::getTelegramUserData($dto);

        // очищаем сообщения
        if ($statusDelete) {
            Message::where('bot_user_id', $botUser->id)->delete();
        }

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
        $payload = $this->basicPayload;
        $payload['message']['text'] = 'Тестовое сообщение';

        $this->sendTestQuery($payload);
    }

    public function test_send_photo(): void
    {
        $fileId = config('testing.tg_file.photo');

        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            [
                'file_id' => $fileId,
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_document(): void
    {
        $fileId = config('testing.tg_file.document');

        $payload = $this->basicPayload;
        $payload['message']['document'] = [
            'file_id' => $fileId,
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_sticker(): void
    {
        $fileId = config('testing.tg_file.sticker');

        $payload = $this->basicPayload;
        $payload['message']['sticker'] = [
            'emoji' => '👍',
            'file_id' => $fileId,
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_location(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['location'] = [
            'latitude' => 55.728387,
            'longitude' => 37.611953,
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_video_note(): void
    {
        $fileId = config('testing.tg_file.video_note');

        $payload = $this->basicPayload;
        $payload['message']['video_note'] = [
            'file_id' => $fileId,
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_voice(): void
    {
        $fileId = config('testing.tg_file.voice');

        $payload = $this->basicPayload;
        $payload['message']['voice'] = [
            'file_id' => $fileId,
        ];

        $this->sendTestQuery($payload);
    }

    public function test_send_contact(): void
    {
        $phone = '79999999999';
        $firstName = 'Тестовый';
        $lastName = 'Тест';

        $payload = $this->basicPayload;
        $payload['message']['contact'] = [
            'phone_number' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'user_id' => config('testing.tg_private.chat_id'),
        ];

        $this->sendTestQuery($payload);
    }
}
