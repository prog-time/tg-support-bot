<?php

namespace Tests\Feature\Modules\Ai;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\AiBotWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiBotWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private int $mainBotId;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->secret = 'test_ai_bot_secret';
        $this->mainBotId = 777;

        config([
            'traffic_source.settings.telegram_ai.secret' => $this->secret,
            'traffic_source.settings.telegram.bot_id' => $this->mainBotId,
            'ai.enabled' => true,
            'app.manager_interface' => 'telegram_group',
        ]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 77;
        $this->botUser->is_banned = false;
        $this->botUser->is_closed = false;
        $this->botUser->save();
    }

    /**
     * Build a valid Telegram update payload from the main bot.
     *
     * @return array
     */
    private function validPayload(): array
    {
        return [
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
                'message_thread_id' => 77,
                'date' => time(),
                'text' => 'Hello from user',
            ],
        ];
    }

    public function test_webhook_returns_204_with_valid_secret(): void
    {
        $response = $this->postJson(
            '/api/ai-bot/webhook',
            $this->validPayload(),
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertNoContent();
    }

    public function test_webhook_returns_403_without_secret(): void
    {
        $response = $this->postJson('/api/ai-bot/webhook', $this->validPayload());

        $response->assertForbidden();
    }

    public function test_webhook_returns_403_with_wrong_secret(): void
    {
        $response = $this->postJson(
            '/api/ai-bot/webhook',
            $this->validPayload(),
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong_secret']
        );

        $response->assertForbidden();
    }

    public function test_webhook_dispatches_ai_bot_webhook_job(): void
    {
        $this->postJson(
            '/api/ai-bot/webhook',
            $this->validPayload(),
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        Queue::assertPushed(AiBotWebhookJob::class);
    }
}
