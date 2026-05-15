<?php

namespace Tests\Unit\Modules\Ai\Services;

use App\Models\BotUser;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShouldAiReplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
    }

    private function makeUpdate(
        ?string $text = 'привет',
        string $typeSource = 'private',
        string $typeQuery = 'message'
    ): TelegramUpdateDto {
        return new TelegramUpdateDto(
            updateId: 1,
            typeQuery: $typeQuery,
            aiTechMessage: false,
            typeSource: $typeSource,
            chatId: 100,
            text: $text,
        );
    }

    private function makeBotUser(bool $banned = false, bool $closed = false): BotUser
    {
        $botUser = new BotUser();
        $botUser->forceFill([
            'id' => 1,
            'is_banned' => $banned,
            'is_closed' => $closed,
        ]);

        return $botUser;
    }

    public function test_returns_true_on_happy_path(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            $this->makeBotUser(),
        );

        $this->assertTrue($result);
    }

    public function test_returns_false_when_ai_disabled(): void
    {
        config([
            'ai.enabled' => false,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_admin_panel_interface(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'admin_panel',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_non_private_chat(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(typeSource: 'supergroup'),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_callback_query(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(typeQuery: 'callback_query'),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_slash_command(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(text: '/start'),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_empty_text(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(text: '   '),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_for_null_text(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(text: null),
            $this->makeBotUser(),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_when_bot_user_is_null(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            null,
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_when_bot_user_is_banned(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            $this->makeBotUser(banned: true),
        );

        $this->assertFalse($result);
    }

    public function test_returns_false_when_bot_user_is_closed(): void
    {
        config([
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $result = (new ShouldAiReply())->shouldGenerateForUserMessage(
            $this->makeUpdate(),
            $this->makeBotUser(closed: true),
        );

        $this->assertFalse($result);
    }
}
