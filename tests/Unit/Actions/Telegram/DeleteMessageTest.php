<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\DeleteMessage;
use App\DTOs\TGTextMessageDto;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeleteMessageTest extends TestCase
{

    public function test_execute_returns_telegram_answer_dto_on_success(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $dto = new TGTextMessageDto(
            methodQuery: 'deleteMessage',
            token: null,
            typeSource: null,
            chat_id: 123456789,
            message_id: 100,
            message_thread_id: null,
            text: null,
            caption: null,
        );

        $result = DeleteMessage::execute($dto);

        $this->assertNotNull($result);
        $this->assertTrue($result->ok);
    }

    public function test_execute_sends_correct_parameters(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $chatId = 123456789;
        $messageId = 100;

        $dto = new TGTextMessageDto(
            methodQuery: 'deleteMessage',
            token: null,
            typeSource: null,
            chat_id: $chatId,
            message_id: $messageId,
            message_thread_id: null,
            text: null,
            caption: null,
        );

        DeleteMessage::execute($dto);

        $sentRequests = Http::recorded();
        $this->assertCount(1, $sentRequests);

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertStringContainsString('deleteMessage', $request->url());
        $this->assertEquals($chatId, $request['chat_id']);
        $this->assertEquals($messageId, $request['message_id']);
    }

    public function test_execute_returns_dto_on_api_error(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: message to delete not found',
            ], 400),
        ]);

        $dto = new TGTextMessageDto(
            methodQuery: 'deleteMessage',
            token: null,
            typeSource: null,
            chat_id: 123456789,
            message_id: 999999,
            message_thread_id: null,
            text: null,
            caption: null,
        );

        $result = DeleteMessage::execute($dto);

        $this->assertNotNull($result);
        $this->assertFalse($result->ok);
    }

    public function test_execute_includes_message_thread_id_when_provided(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $chatId = 123456789;
        $messageId = 100;
        $threadId = 50;

        $dto = new TGTextMessageDto(
            methodQuery: 'deleteMessage',
            token: null,
            typeSource: null,
            chat_id: $chatId,
            message_id: $messageId,
            message_thread_id: $threadId,
            text: null,
            caption: null,
        );

        DeleteMessage::execute($dto);

        $sentRequests = Http::recorded();

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertEquals($threadId, $request['message_thread_id']);
    }
}
