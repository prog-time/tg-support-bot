<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendAiAnswerMessage;
use App\Models\BotUser;
use Tests\Unit\Actions\Ai\AiActionTest;

class SendAiAnswerMessageTest extends AiActionTest
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();
    }

    public function test_generate_ai_message(): void
    {
        $resultGenerateMessage = $this->sendGenerateAiMessage('Напиши приветствие');

        $callbackData = "ai_message_send_{$resultGenerateMessage->message_id}";

        $dto = $this->createDto($resultGenerateMessage->message_id, $callbackData);

        $this->createAiMessage($this->botUser->id, $dto->messageId);

        $resultAiQuery = (new SendAiAnswerMessage())->execute($dto);
        $this->assertTrue($resultAiQuery->ok);

        $this->assertEquals($resultAiQuery->response_code, 200);
    }
}
