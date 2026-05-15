<?php

namespace Tests\Unit\Helpers;

use App\Helpers\AiHelper;
use Tests\TestCase;

class AiHelperTest extends TestCase
{
    public function test_prepares_ai_answer_correctly(): void
    {
        $managerText = 'Сделай это';
        $aiText = 'Готово';

        $expected = "📄 Инструкция: \nСделай это  \n\n🤖 Ответ от AI: \nГотово \n\n";

        $this->assertEquals($expected, AiHelper::preparedAiAnswer($managerText, $aiText));
    }

    public function test_prepares_ai_answer_without_instruction_when_manager_text_empty(): void
    {
        $aiText = 'Готово';

        $expected = "🤖 Ответ от AI: \nГотово \n\n";

        $this->assertEquals($expected, AiHelper::preparedAiAnswer('', $aiText));
    }

    public function test_prepares_ai_reply_markup_correctly(): void
    {
        $messageId = 123;
        $aiText = 'Готово';

        $expected = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Отправить',
                        'callback_data' => 'ai_message_send_123',
                    ],
                    [
                        'text' => '❌ Отменить',
                        'callback_data' => 'ai_message_cancel_123',
                    ],
                ],
                [
                    [
                        'text' => '📝 Редактировать ответ',
                        'switch_inline_query_current_chat' => "ai_message_edit_123 \n\nГотово",
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, AiHelper::preparedAiReplyMarkup($messageId, $aiText));
    }
}
