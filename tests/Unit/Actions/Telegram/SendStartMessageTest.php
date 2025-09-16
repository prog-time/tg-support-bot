<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendStartMessage;
use App\DTOs\TelegramUpdateDto;
use Illuminate\Http\Request;
use Tests\TestCase;

class SendStartMessageTest extends TestCase
{
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payload = [
            'update_id' => 518622265,
            'message' => [
                'message_id' => 1901,
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'type' => 'private',
                ],
                'date' => 1757448913,
                'text' => '/start',
                'entities' => [
                    [
                        'offset' => 0,
                        'length' => 6,
                        'type' => 'bot_command',
                    ],
                ],
            ],
        ];
    }

    public function test_send_start_message(): void
    {
        // Оборачиваем в Request
        $request = Request::create('api/telegram/bot', 'POST', $this->payload);

        // Вызываем фабрику DTO
        $dto = TelegramUpdateDto::fromRequest($request);

        // Act
        $result = (new SendStartMessage())->execute($dto);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($result->error_code, 200);

        $this->assertEquals(__('messages.start'), $result->text);

        $this->assertNotEmpty($result->rawData);
    }
}
