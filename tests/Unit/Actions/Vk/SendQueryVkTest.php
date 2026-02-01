<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Vk\SendQueryVk;
use App\DTOs\Vk\VkTextMessageDto;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendQueryVkTest extends TestCase
{
    private int $peerId;

    public function setUp(): void
    {
        parent::setUp();
        $this->peerId = time();
    }

    public function test_execute_returns_vk_answer_dto_on_success(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'response' => 12345,
            ], 200),
        ]);

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.send',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: null,
            message: 'Тестовое сообщение',
            attachment: null,
        );

        $result = SendQueryVk::execute($dto);

        $this->assertNotNull($result);
        $this->assertEquals(200, $result->response_code);
        $this->assertEquals(12345, $result->response);
    }

    public function test_execute_sends_correct_parameters(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'response' => 12345,
            ], 200),
        ]);

        $message = 'Тестовое сообщение';

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.send',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: null,
            message: $message,
            attachment: null,
        );

        SendQueryVk::execute($dto);

        $sentRequests = Http::recorded();
        $this->assertCount(1, $sentRequests);

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertStringContainsString('messages.send', $request->url());
        $this->assertEquals($this->peerId, $request['peer_id']);
        $this->assertEquals($message, $request['message']);
    }

    public function test_execute_returns_dto_on_api_error(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'error' => [
                    'error_code' => 100,
                    'error_msg' => 'One of the parameters specified was missing or invalid',
                ],
            ], 200),
        ]);

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.send',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: null,
            message: 'Тест',
            attachment: null,
        );

        $result = SendQueryVk::execute($dto);

        $this->assertNotNull($result);
        $this->assertEquals(500, $result->response_code);
        $this->assertNotNull($result->error_message);
    }

    public function test_execute_with_attachment(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'response' => 12345,
            ], 200),
        ]);

        $attachment = 'photo123456_789';

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.send',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: null,
            message: 'Сообщение с вложением',
            attachment: $attachment,
        );

        SendQueryVk::execute($dto);

        $sentRequests = Http::recorded();

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertEquals($attachment, $request['attachment']);
    }

    public function test_execute_with_keyboard(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'response' => 12345,
            ], 200),
        ]);

        $keyboard = json_encode([
            'one_time' => true,
            'buttons' => [
                [
                    ['action' => ['type' => 'text', 'label' => 'Кнопка']],
                ],
            ],
        ]);

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.send',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: null,
            message: 'Сообщение с клавиатурой',
            attachment: null,
            keyboard: $keyboard,
        );

        SendQueryVk::execute($dto);

        $sentRequests = Http::recorded();

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertEquals($keyboard, $request['keyboard']);
    }

    public function test_execute_with_different_method(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.delete' => Http::response([
                'response' => 1,
            ], 200),
        ]);

        $dto = new VkTextMessageDto(
            methodQuery: 'messages.delete',
            title: null,
            file: null,
            peer_id: $this->peerId,
            message_id: 12345,
            message: null,
            attachment: null,
        );

        $result = SendQueryVk::execute($dto);

        $sentRequests = Http::recorded();

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertStringContainsString('messages.delete', $request->url());
        $this->assertNotNull($result);
        $this->assertEquals(200, $result->response_code);
    }
}
