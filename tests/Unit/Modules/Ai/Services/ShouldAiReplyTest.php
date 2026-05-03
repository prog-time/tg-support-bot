<?php

namespace Tests\Unit\Modules\Ai\Services;

use App\Models\BotUser;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class ShouldAiReplyTest extends TestCase
{
    use RefreshDatabase;

    private ShouldAiReply $service;

    private int $mainBotId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ShouldAiReply();
        $this->mainBotId = 999;

        config([
            'ai.enabled' => true,
            'traffic_source.settings.telegram.bot_id' => $this->mainBotId,
        ]);
    }

    /**
     * Build a TelegramUpdateDto for a supergroup message whose sender is the main bot.
     *
     * @param array $overrides
     *
     * @return TelegramUpdateDto
     */
    private function makeSupergroupUpdateFromMainBot(array $overrides = []): TelegramUpdateDto
    {
        $params = array_merge([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => $this->mainBotId,
                    'is_bot' => true,
                    'first_name' => 'MainBot',
                    'username' => 'main_bot',
                ],
                'chat' => [
                    'id' => -100123456789,
                    'title' => 'Support Group',
                    'type' => 'supergroup',
                ],
                'message_thread_id' => 42,
                'date' => time(),
                'text' => 'User message forwarded by main bot',
            ],
        ], $overrides);

        $request = Request::create('/api/ai-bot/webhook', 'POST', $params);

        return TelegramUpdateDto::fromRequest($request);
    }

    /**
     * Build an active, non-banned BotUser with a topic.
     *
     * @return BotUser
     */
    private function makeActiveBotUser(): BotUser
    {
        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->topic_id = 42;
        $botUser->is_banned = false;
        $botUser->is_closed = false;
        $botUser->save();

        return $botUser;
    }

    public function test_returns_true_when_all_rules_pass(): void
    {
        $update = $this->makeSupergroupUpdateFromMainBot();
        $botUser = $this->makeActiveBotUser();

        $this->assertTrue($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_ai_globally_disabled(): void
    {
        config(['ai.enabled' => false]);

        $update = $this->makeSupergroupUpdateFromMainBot();
        $botUser = $this->makeActiveBotUser();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_message_not_in_supergroup(): void
    {
        // Private chat message
        $params = [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => $this->mainBotId,
                    'is_bot' => true,
                    'first_name' => 'MainBot',
                    'username' => 'main_bot',
                ],
                'chat' => [
                    'id' => 111,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'hello',
            ],
        ];

        $request = Request::create('/api/ai-bot/webhook', 'POST', $params);
        $update = TelegramUpdateDto::fromRequest($request);
        $botUser = $this->makeActiveBotUser();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_message_missing_thread_id(): void
    {
        // Supergroup but no message_thread_id (not a topic message)
        $params = [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => $this->mainBotId,
                    'is_bot' => true,
                    'first_name' => 'MainBot',
                    'username' => 'main_bot',
                ],
                'chat' => [
                    'id' => -100123456789,
                    'title' => 'Support Group',
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'text' => 'hello',
            ],
        ];

        $request = Request::create('/api/ai-bot/webhook', 'POST', $params);
        $update = TelegramUpdateDto::fromRequest($request);
        $botUser = $this->makeActiveBotUser();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_sender_is_not_main_bot(): void
    {
        // Sender is a manager (human), not the main bot
        $params = [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => 77777,  // different id — this is a manager
                    'is_bot' => false,
                    'first_name' => 'Manager',
                    'username' => 'manager_user',
                ],
                'chat' => [
                    'id' => -100123456789,
                    'title' => 'Support Group',
                    'type' => 'supergroup',
                ],
                'message_thread_id' => 42,
                'date' => time(),
                'text' => 'Reply from manager',
            ],
        ];

        $request = Request::create('/api/ai-bot/webhook', 'POST', $params);
        $update = TelegramUpdateDto::fromRequest($request);
        $botUser = $this->makeActiveBotUser();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_bot_user_is_null(): void
    {
        $update = $this->makeSupergroupUpdateFromMainBot();

        $this->assertFalse($this->service->shouldReply($update, null));
    }

    public function test_returns_false_when_user_is_banned(): void
    {
        $update = $this->makeSupergroupUpdateFromMainBot();

        $botUser = $this->makeActiveBotUser();
        $botUser->is_banned = true;
        $botUser->save();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_user_topic_is_closed(): void
    {
        $update = $this->makeSupergroupUpdateFromMainBot();

        $botUser = $this->makeActiveBotUser();
        $botUser->is_closed = true;
        $botUser->save();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }

    public function test_returns_false_when_main_bot_id_is_zero(): void
    {
        config(['traffic_source.settings.telegram.bot_id' => 0]);

        $update = $this->makeSupergroupUpdateFromMainBot();
        $botUser = $this->makeActiveBotUser();

        $this->assertFalse($this->service->shouldReply($update, $botUser));
    }
}
