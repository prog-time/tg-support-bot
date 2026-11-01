<?php

namespace Tests\Unit\Modules\Telegram\Controllers;

use App\Models\BotUser;
use App\Modules\Ai\Actions\AiAcceptMessage;
use App\Modules\Ai\Actions\AiCancelMessage;
use App\Modules\Ai\Actions\EditAiMessage;
use App\Modules\Telegram\Controllers\TelegramBotController;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
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

    private function callbackRequest(string $data, string $chatType, int $threadOrChat): Request
    {
        return Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999100,
            'callback_query' => [
                'id' => 42,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Manager'],
                'data' => $data,
                'message' => [
                    'message_id' => 321,
                    'message_thread_id' => $threadOrChat,
                    'chat' => ['id' => $chatType === 'private' ? $threadOrChat : -100555, 'type' => $chatType],
                    'text' => 'draft',
                ],
            ],
        ]);
    }

    private function groupUser(): BotUser
    {
        $botUser = BotUser::getUserByChatId(777001, 'telegram');
        $botUser->update(['topic_id' => 91]);

        return $botUser->fresh();
    }

    public function test_send_button_in_supergroup_calls_accept_action(): void
    {
        $this->groupUser();
        $accept = Mockery::mock(AiAcceptMessage::class);
        $accept->shouldReceive('execute')->once();
        $this->app->instance(AiAcceptMessage::class, $accept);

        (new TelegramBotController($this->callbackRequest('ai_message_send_321', 'supergroup', 91)))->bot_query();
    }

    public function test_cancel_button_in_supergroup_calls_cancel_action(): void
    {
        $this->groupUser();
        $cancel = Mockery::mock(AiCancelMessage::class);
        $cancel->shouldReceive('execute')->once();
        $this->app->instance(AiCancelMessage::class, $cancel);

        (new TelegramBotController($this->callbackRequest('ai_message_cancel_321', 'supergroup', 91)))->bot_query();
    }

    public function test_ai_buttons_outside_supergroup_are_ignored(): void
    {
        BotUser::getUserByChatId(777002, 'telegram');
        $accept = Mockery::mock(AiAcceptMessage::class);
        $accept->shouldNotReceive('execute');
        $this->app->instance(AiAcceptMessage::class, $accept);

        (new TelegramBotController($this->callbackRequest('ai_message_send_321', 'private', 777002)))->bot_query();
    }

    public function test_malformed_ai_callback_data_is_ignored(): void
    {
        $this->groupUser();
        $accept = Mockery::mock(AiAcceptMessage::class);
        $accept->shouldNotReceive('execute');
        $cancel = Mockery::mock(AiCancelMessage::class);
        $cancel->shouldNotReceive('execute');
        $this->app->instance(AiAcceptMessage::class, $accept);
        $this->app->instance(AiCancelMessage::class, $cancel);

        foreach (['ai_message_send_', 'ai_message_send_1x', 'ai_message_cancel_1_2', "ai_message_send_1\n"] as $data) {
            (new TelegramBotController($this->callbackRequest($data, 'supergroup', 91)))->bot_query();
        }
    }

    public function test_edit_command_runs_edit_action_on_any_platform_and_never_reaches_the_user(): void
    {
        $botUser = $this->groupUser();
        $botUser->update(['platform' => 'vk']);
        $edit = Mockery::mock(EditAiMessage::class);
        $edit->shouldReceive('execute')->once();
        $this->app->instance(EditAiMessage::class, $edit);
        Queue::fake();

        (new TelegramBotController(Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999101,
            'message' => [
                'message_id' => 12,
                'message_thread_id' => 91,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Manager'],
                'chat' => ['id' => -100555, 'type' => 'supergroup'],
                'text' => "@main_bot ai_message_edit_321 \n\nnew text",
            ],
        ])))->bot_query();

        Queue::assertNothingPushed();
    }

    public function test_bot_mention_without_command_is_handled_as_a_regular_message(): void
    {
        $this->groupUser();
        $edit = Mockery::mock(EditAiMessage::class);
        $edit->shouldNotReceive('execute');
        $this->app->instance(EditAiMessage::class, $edit);
        Queue::fake();

        (new TelegramBotController(Request::create('api/telegram/bot', 'POST', [
            'update_id' => 999102,
            'message' => [
                'message_id' => 13,
                'message_thread_id' => 91,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Manager'],
                'chat' => ['id' => -100555, 'type' => 'supergroup'],
                'text' => '@main_bot спасибо за ответ',
            ],
        ])))->bot_query();

        Queue::assertPushed(SendTelegramMessageJob::class);
    }
}
