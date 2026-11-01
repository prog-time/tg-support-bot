<?php

namespace Tests\Unit\Modules\Ai\Jobs;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\DTOs\AiResponseDto;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendAiReplyJobTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsService::class);
        $settings->set('ai.default_provider', 'openai');
        $settings->set('telegram.token', 'main_bot_token');
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-100111222333');

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 77;
        $this->botUser->save();

        Queue::fake();
    }

    private function aiService(string $text = 'Auto reply'): AiAssistantService
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

    private function job(): SendAiReplyJob
    {
        return new SendAiReplyJob($this->botUser->id, TelegramUpdateDtoMock::getDto(), 'user question');
    }

    public function test_main_bot_posts_labelled_reply_and_user_gets_it_without_label(): void
    {
        Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 555]])]);

        $this->job()->handle($this->aiService());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'botmain_bot_token/sendMessage')
            && $request['text'] === "🤖 AI:\nAuto reply"
            && !isset($request['parse_mode']));

        $this->assertDatabaseHas('ai_messages', [
            'bot_user_id' => $this->botUser->id,
            'message_id' => 555,
            'status' => AiMessage::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Auto reply',
        ]);
        $this->assertSame(1, Message::where('bot_user_id', $this->botUser->id)->where('message_type', 'outgoing')->count());
        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    public function test_delivers_without_group_post_when_group_is_not_configured(): void
    {
        app(SettingsService::class)->forget('telegram.group_id');
        Http::fake();

        $this->job()->handle($this->aiService());

        Http::assertNothingSent();
        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => AiMessage::STATUS_ACCEPTED]);
        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    public function test_failed_topic_post_still_delivers_to_user(): void
    {
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests'], 429)]);

        $this->job()->handle($this->aiService());

        $this->assertDatabaseHas('ai_messages', ['message_id' => null, 'status' => AiMessage::STATUS_ACCEPTED]);
        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    public function test_last_attempt_without_topic_still_delivers_to_user(): void
    {
        $this->botUser->update(['topic_id' => null]);
        Http::fake();

        $queueJob = $this->createMock(Job::class);
        $queueJob->method('attempts')->willReturn(3);
        $queueJob->method('getConnectionName')->willReturn('database');

        $job = $this->job();
        $job->setJob($queueJob);
        $job->handle($this->aiService());

        Http::assertNothingSent();
        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    public function test_auto_reply_persists_message_without_update_dto(): void
    {
        app(SettingsService::class)->forget('telegram.group_id');

        (new SendAiReplyJob($this->botUser->id, null, 'user question'))->handle($this->aiService());

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
        ]);
    }
}
