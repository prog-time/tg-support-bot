<?php

namespace Tests\Unit\Modules\Ai\Jobs;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Ai\DTOs\AiResponseDto;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Services\AiAssistantService;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiDraftJobTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_TOKEN = 'main_bot_token';

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsService::class);
        $settings->set('telegram.token', self::MAIN_TOKEN);
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-100987654321');
        $settings->set('ai.default_provider', 'openai');

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 77;
        $this->botUser->save();

        Queue::fake();
    }

    private function aiService(string $text = 'Black <b>tea</b> is fine'): AiAssistantService
    {
        $service = $this->createMock(AiAssistantService::class);
        $service->method('processMessage')->willReturn(new AiResponseDto(
            response: $text,
            confidenceScore: 0.9,
            shouldEscalate: false,
            provider: 'openai',
            modelUsed: 'gpt-4',
            tokensUsed: 10,
            responseTime: 0.5,
        ));

        return $service;
    }

    private function job(): SendAiDraftJob
    {
        return new SendAiDraftJob($this->botUser->id, TelegramUpdateDtoMock::getDto(), 'user question');
    }

    public function test_main_bot_posts_labelled_plain_draft_with_buttons(): void
    {
        Http::fake([
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 321]]),
            '*/editMessageReplyMarkup' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', [
            'bot_user_id' => $this->botUser->id,
            'message_id' => 321,
            'text_ai' => 'Black <b>tea</b> is fine',
            'status' => 'pending',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'bot' . self::MAIN_TOKEN . '/sendMessage')
            && $request['text'] === "🤖 AI:\nBlack tea is fine"
            && !isset($request['parse_mode']));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'bot' . self::MAIN_TOKEN . '/editMessageReplyMarkup')
            && $request['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'ai_message_send_321');
    }

    public function test_keeps_draft_for_admin_panel_when_group_is_not_configured(): void
    {
        app(SettingsService::class)->forget('telegram.group_id');
        Http::fake();

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', [
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'status' => 'pending',
        ]);
        Http::assertNothingSent();
    }

    public function test_keeps_draft_when_telegram_is_not_connected(): void
    {
        app(SettingsService::class)->forget('telegram.token');
        Http::fake();

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => 'pending']);
        Http::assertNothingSent();
    }

    public function test_keeps_draft_and_logs_without_token_when_publication_fails(): void
    {
        Http::fake(['*' => Http::response([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests',
            'parameters' => ['retry_after' => 5],
        ], 429)]);

        $logged = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info', 'warning', 'debug');
        Log::shouldReceive('log');
        Log::shouldReceive('error')->andReturnUsing(function ($message, $context = []) use (&$logged) {
            $logged[] = [$message, $context];
        });

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => 'pending']);
        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString(self::MAIN_TOKEN, (string) json_encode($logged));
    }

    public function test_logs_when_buttons_cannot_be_attached(): void
    {
        Http::fake([
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 321]]),
            '*/editMessageReplyMarkup' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'], 400),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info', 'warning', 'debug');
        Log::shouldReceive('log');
        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn ($message, $context = []) => is_array($context) && ($context['method'] ?? null) === 'editMessageReplyMarkup'
        );

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => 321, 'status' => 'pending']);
    }

    public function test_does_not_throw_when_ai_returns_null(): void
    {
        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->method('processMessage')->willReturn(null);

        $this->job()->handle($aiService);

        $this->assertSame(0, AiMessage::count());
    }

    public function test_waits_for_topic_before_generating(): void
    {
        $this->botUser->update(['topic_id' => null]);

        $aiService = $this->createMock(AiAssistantService::class);
        $aiService->expects($this->never())->method('processMessage');
        Http::fake();

        $this->job()->handle($aiService);

        $this->assertSame(0, AiMessage::count());
    }

    public function test_last_attempt_without_topic_keeps_draft_for_admin_panel(): void
    {
        $this->botUser->update(['topic_id' => null]);
        Http::fake();

        $queueJob = $this->createMock(Job::class);
        $queueJob->method('attempts')->willReturn(3);
        $queueJob->method('getConnectionName')->willReturn('database');

        $job = $this->job();
        $job->setJob($queueJob);
        $job->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => 'pending']);
        Http::assertNothingSent();
    }

    public function test_sync_queue_without_topic_keeps_draft_for_admin_panel(): void
    {
        $this->botUser->update(['topic_id' => null]);
        Http::fake();

        $queueJob = $this->createMock(Job::class);
        $queueJob->method('attempts')->willReturn(1);
        $queueJob->method('getConnectionName')->willReturn('sync');

        $job = $this->job();
        $job->setJob($queueJob);
        $job->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => 'pending']);
    }
}
