<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiCancelMessage;
use App\Models\BotUser;

class AiCancelMessageTest extends AiActionTest
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();
    }

    public function test_cancel_ai_message(): void
    {
        $resultGenerateMessage = $this->sendGenerateAiMessage();

        $callbackData = "ai_message_cancel_{$resultGenerateMessage->message_id}";

        $dto = $this->createDto($resultGenerateMessage->message_id, $callbackData);

        $aiMessage = $this->createAiMessage($this->botUser->id, $dto->messageId);

        $resultAiQuery = (new AiCancelMessage())->execute($dto);
        $this->assertTrue($resultAiQuery);
    }
}
