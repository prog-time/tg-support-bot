<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiAcceptMessage;
use App\Models\BotUser;

class AiAcceptMessageTest extends AiActionTest
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();
    }

    public function test_accept_ai_message(): void
    {
        $resultGenerateMessage = $this->sendGenerateAiMessage();

        $callbackData = "ai_message_send_{$resultGenerateMessage->message_id}";

        $dto = $this->createDto($resultGenerateMessage->message_id, $callbackData);

        $this->createAiMessage($this->botUser->id, $dto->messageId);

        $resultAiQuery = (new AiAcceptMessage())->execute($dto);
        $this->assertTrue($resultAiQuery);
    }
}
