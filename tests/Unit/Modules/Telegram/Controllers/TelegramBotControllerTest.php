<?php

namespace Tests\Unit\Modules\Telegram\Controllers;

use App\Modules\Telegram\Controllers\TelegramBotController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
}
