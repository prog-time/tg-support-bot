<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiAction;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiActionTest extends TestCase
{
    use RefreshDatabase;

    private AiAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new class extends AiAction {
            public function execute(TelegramUpdateDto $update): void {}
        };
    }

    public function test_get_message_data_returns_null_when_not_found(): void
    {
        $result = $this->action->getMessageDataByCallbackData('ai_message_edit_0_nonexistent');

        $this->assertNull($result);
    }

    public function test_get_message_data_returns_ai_message_when_found(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $aiMessage = AiMessage::create([
            'bot_user_id' => $botUser->id,
            'message_id'  => '123',
            'text_ai'     => 'AI response',
        ]);

        $result = $this->action->getMessageDataByCallbackData('ai_message_edit_123');

        $this->assertInstanceOf(AiMessage::class, $result);
        $this->assertEquals($aiMessage->id, $result->id);
    }

    public function test_get_message_data_returns_null_when_callback_data_has_no_id(): void
    {
        $result = $this->action->getMessageDataByCallbackData('ai_message_edit');

        $this->assertNull($result);
    }
}
