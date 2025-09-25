<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendAiAnswerMessage;
use Tests\Unit\Actions\Ai\AiActionTest;

class SendAiAnswerMessageTest extends AiActionTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_generate_ai_message(): void
    {
        $testMessage = 'Напиши приветствие';
        $resultGenerateMessage = $this->sendGenerateAiMessage($testMessage);

        $dto = $this->createDto($resultGenerateMessage->message_id, null, $testMessage);

        $resultAiQuery = (new SendAiAnswerMessage())->execute($dto);
        $this->assertTrue($resultAiQuery->ok);

        $this->assertEquals($resultAiQuery->response_code, 200);
    }
}
