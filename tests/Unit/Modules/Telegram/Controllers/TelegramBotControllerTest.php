<?php

namespace Tests\Unit\Modules\Telegram\Controllers;

use App\Models\BotUser;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use App\Modules\Telegram\Controllers\TelegramBotController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * issue #46 — when a Telegram webhook update can't be resolved to a known
 * sender (unrecognized top-level update type, or no matching BotUser),
 * TelegramBotController must log to the `telegram` channel instead of
 * silently dropping the update, while still aborting with 200 (a non-2xx
 * response makes Telegram retry indefinitely).
 *
 * Constructed directly (bypassing the HTTP kernel / TelegramQuery
 * middleware) so the only Log:: call in play is the one under test —
 * TelegramQuery::logRequest() also calls Log::channel('app') on every
 * request, which would collide with a full-request Log mock.
 */
class TelegramBotControllerTest extends TestCase
{
    use RefreshDatabase;

    private function assertAbortsWith200(Request $request): void
    {
        try {
            new TelegramBotController($request);
            $this->fail('Expected the constructor to abort(200)');
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getStatusCode());
        }
    }

    public function test_unrecognized_update_type_logs_to_telegram_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('telegram')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'unrecognized update')
                && array_key_exists('keys', $context)
        );

        // No 'message' / 'edited_message' / 'callback_query' / 'inline_query' /
        // 'chat_member' key — TelegramUpdateDto::detectType() returns 'unknown'.
        $request = Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999001,
            'poll_answer' => ['poll_id' => 'abc'],
        ]);

        $this->assertAbortsWith200($request);
    }

    public function test_unresolvable_bot_user_logs_to_telegram_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('telegram')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'could not resolve BotUser')
                && $context['type_source'] === 'supergroup'
        );

        // A supergroup message whose message_thread_id matches no existing
        // BotUser topic — getByTopicId() returns null.
        $request = Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999002,
            'message' => [
                'message_id' => 1,
                'message_thread_id' => 999999999,
                'from' => [
                    'id' => 555,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => -100999999,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'text' => 'orphaned topic message',
            ],
        ]);

        $this->assertAbortsWith200($request);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function okTopicUpdate(array $message): Request
    {
        BotUser::create(['chat_id' => 'chat:C900', 'platform' => 'ok', 'topic_id' => 910]);

        return Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999200,
            'message' => $message + [
                'message_id' => 20,
                'message_thread_id' => 910,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Manager'],
                'chat' => ['id' => -100555, 'type' => 'supergroup'],
            ],
        ]);
    }

    public function test_manager_text_in_ok_topic_is_delivered_to_ok(): void
    {
        Queue::fake();

        (new TelegramBotController($this->okTopicUpdate(['text' => 'Ответ'])))->bot_query();

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->chat_id === 'chat:C900'
            && $job->queryParams->text === 'Ответ');
        Queue::assertNotPushed(SendWebhookMessage::class);
    }

    public function test_edit_in_ok_topic_is_not_sent_to_the_user(): void
    {
        Queue::fake();
        BotUser::create(['chat_id' => 'chat:C901', 'platform' => 'ok', 'topic_id' => 911]);

        (new TelegramBotController(Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999201,
            'edited_message' => [
                'message_id' => 21,
                'message_thread_id' => 911,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Manager'],
                'chat' => ['id' => -100555, 'type' => 'supergroup'],
                'text' => 'Исправлено',
            ],
        ])))->bot_query();

        Queue::assertNothingPushed();
    }
}
