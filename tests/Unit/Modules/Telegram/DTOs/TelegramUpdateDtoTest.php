<?php

namespace Tests\Unit\Modules\Telegram\DTOs;

use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramUpdateDtoTest extends TestCase
{
    private function dto(string $text, string $chatType = 'supergroup', string $updateKey = 'message'): TelegramUpdateDto
    {
        return TelegramUpdateDto::fromRequest(Request::create('api/telegram/bot', 'POST', [
            'update_id' => 1,
            $updateKey => [
                'message_id' => 5,
                'message_thread_id' => 7,
                'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'M'],
                'chat' => ['id' => -100, 'type' => $chatType],
                'text' => $text,
            ],
        ]));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function supergroupTexts(): array
    {
        return [
            'button text' => ["@main_bot ai_message_edit_12 \n\nnew text", true],
            'without username' => ["ai_message_edit_12\nnew text", true],
            'bot mention only' => ['@main_bot подскажи, что ответить', false],
            'command quoted mid-text' => ['смотри ai_message_edit_12 в логе', false],
            'command without id' => ['@main_bot ai_message_edit_', false],
            'plain text' => ['привет', false],
        ];
    }

    #[DataProvider('supergroupTexts')]
    public function test_detects_ai_edit_command_in_supergroup(string $text, bool $expected): void
    {
        $this->assertSame($expected, $this->dto($text)->aiTechMessage);
    }

    public function test_private_chat_text_is_never_an_edit_command(): void
    {
        $this->assertFalse($this->dto('ai_message_edit_12 hack', 'private')->aiTechMessage);
    }

    public function test_edited_message_is_never_an_edit_command(): void
    {
        $this->assertFalse($this->dto('ai_message_edit_12 hack', 'supergroup', 'edited_message')->aiTechMessage);
    }
}
