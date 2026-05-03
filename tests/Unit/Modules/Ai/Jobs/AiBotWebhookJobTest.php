<?php

namespace Tests\Unit\Modules\Ai\Jobs;

use App\Models\BotUser;
use App\Modules\Ai\Jobs\AiBotWebhookJob;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Ai\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class AiBotWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private int $mainBotId;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->mainBotId = 12345;
        config([
            'ai.enabled' => true,
            'traffic_source.settings.telegram.bot_id' => $this->mainBotId,
            'app.manager_interface' => 'telegram_group',
        ]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 42;
        $this->botUser->is_banned = false;
        $this->botUser->is_closed = false;
        $this->botUser->save();
    }

    /**
     * Build a TelegramUpdateDto simulating a message from the main bot in a topic.
     *
     * @return TelegramUpdateDto
     */
    private function makeUpdateFromMainBot(): TelegramUpdateDto
    {
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
                'message_thread_id' => 42,
                'date' => time(),
                'text' => 'Hello from user',
            ],
        ];

        $request = Request::create('/api/ai-bot/webhook', 'POST', $params);

        return TelegramUpdateDto::fromRequest($request);
    }

    public function test_dispatches_send_ai_draft_job_when_auto_reply_is_false(): void
    {
        config(['ai.auto_reply' => false]);

        $update = $this->makeUpdateFromMainBot();
        $job = new AiBotWebhookJob($update);

        $shouldAiReply = new ShouldAiReply();
        $aiService = $this->createMock(AiAssistantService::class);

        $job->handle($shouldAiReply, $aiService);

        Queue::assertPushed(SendAiDraftJob::class, function ($pushed) {
            return $pushed->botUserId === $this->botUser->id;
        });
        Queue::assertNotPushed(SendAiReplyJob::class);
    }

    public function test_dispatches_send_ai_reply_job_when_auto_reply_is_true(): void
    {
        config(['ai.auto_reply' => true]);

        $replyText = 'AI generated answer';
        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('generateReply')->willReturn($replyText);

        $update = $this->makeUpdateFromMainBot();
        $job = new AiBotWebhookJob($update);

        $shouldAiReply = new ShouldAiReply();

        $job->handle($shouldAiReply, $aiService);

        Queue::assertPushed(SendAiReplyJob::class, function ($pushed) use ($replyText) {
            return $pushed->botUserId === $this->botUser->id
                && $pushed->replyText === $replyText;
        });
        Queue::assertNotPushed(SendAiDraftJob::class);
    }

    public function test_does_not_dispatch_when_should_ai_reply_returns_false(): void
    {
        // AI disabled → ShouldAiReply returns false
        config(['ai.enabled' => false]);

        $update = $this->makeUpdateFromMainBot();
        $job = new AiBotWebhookJob($update);

        $shouldAiReply = new ShouldAiReply();
        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->expects($this->never())->method('generateReply');

        $job->handle($shouldAiReply, $aiService);

        Queue::assertNotPushed(SendAiDraftJob::class);
        Queue::assertNotPushed(SendAiReplyJob::class);
    }

    public function test_does_not_dispatch_when_manager_interface_is_admin_panel(): void
    {
        config(['app.manager_interface' => 'admin_panel']);

        $update = $this->makeUpdateFromMainBot();
        $job = new AiBotWebhookJob($update);

        $shouldAiReply = new ShouldAiReply();
        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->expects($this->never())->method('generateReply');

        $job->handle($shouldAiReply, $aiService);

        Queue::assertNotPushed(SendAiDraftJob::class);
        Queue::assertNotPushed(SendAiReplyJob::class);
    }

    public function test_does_not_dispatch_when_generate_reply_returns_null(): void
    {
        config(['ai.auto_reply' => true]);

        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('generateReply')->willReturn(null);

        $update = $this->makeUpdateFromMainBot();
        $job = new AiBotWebhookJob($update);

        $shouldAiReply = new ShouldAiReply();

        $job->handle($shouldAiReply, $aiService);

        Queue::assertNotPushed(SendAiReplyJob::class);
        Queue::assertNotPushed(SendAiDraftJob::class);
    }
}
