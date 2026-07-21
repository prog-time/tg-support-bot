<?php

namespace Tests\Unit\Modules\Ai\Actions;

use App\Jobs\EnrichBotUserProfileJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Actions\DeliverAiAnswerToUser;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\Jobs\SendVkMessageJob;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliverAiAnswerToUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BotUser::truncate();
        Message::truncate();
        Queue::fake();
    }

    public function test_dispatches_simple_telegram_job_for_telegram_user_and_persists_plain_text(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->topic_id = 123;
        $botUser->save();

        $htmlText = '<b>Здравствуйте!</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $htmlText);

        $this->assertTrue($result);

        // Simple (non-saving) job dispatched — NOT the full saving job.
        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendTelegramMessageJob::class);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        // The send job receives PLAIN text and NO parse_mode — identical to the
        // manager reply path. Sending raw AI HTML with parse_mode=html made Telegram
        // reject it ("can't parse entities") so the answer never reached the user.
        $this->assertEquals($botUser->chat_id, $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals('Здравствуйте!', $job->queryParams->text);
        $this->assertNull($job->queryParams->parse_mode);

        // The persisted messages row stores plain text (no HTML tags).
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'text' => 'Здравствуйте!',
        ]);

        $this->assertEquals(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_telegram_plain_text_stored_when_no_html_tags(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->topic_id = 123;
        $botUser->save();

        $plainText = 'Добрый день! Чем могу помочь?';

        (new DeliverAiAnswerToUser())->execute($botUser, $plainText);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => $plainText,
        ]);
    }

    public function test_dispatches_simple_vk_job_for_vk_user_and_persists_plain_text(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'vk');
        $botUser->topic_id = 200;
        $botUser->save();

        $htmlText = '<b>Здравствуйте!</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $htmlText);

        $this->assertTrue($result);

        Queue::assertPushed(SendVkSimpleMessageJob::class);
        Queue::assertNotPushed(SendVkMessageJob::class);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('messages.send', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->peer_id);
        // VK receives plain text (HTML stripped).
        $this->assertEquals('Здравствуйте!', $job->queryParams->message);

        // Outgoing row persisted with plain text.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'vk',
            'message_type' => 'outgoing',
            'text' => 'Здравствуйте!',
        ]);

        $this->assertEquals(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_dispatches_simple_max_job_for_max_user_and_persists_plain_text(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'max');
        $botUser->topic_id = 300;
        $botUser->save();

        $htmlText = '<b>Здравствуйте</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $htmlText);

        $this->assertTrue($result);

        Queue::assertPushed(SendMaxSimpleMessageJob::class);
        Queue::assertNotPushed(SendMaxMessageJob::class);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->user_id);
        // Max receives plain text.
        $this->assertEquals('Здравствуйте', $job->queryParams->text);

        // Outgoing row persisted with plain text.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'max',
            'message_type' => 'outgoing',
            'text' => 'Здравствуйте',
        ]);

        $this->assertEquals(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_dispatches_simple_avito_job_for_avito_user_and_persists_plain_text(): void
    {
        $botUser = BotUser::getUserByChatId('u2i-' . time(), 'avito');
        $botUser->save();

        $htmlText = '<b>Здравствуйте!</b> <i>Спасибо за обращение.</i>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $htmlText);

        $this->assertTrue($result);

        Queue::assertPushed(SendAvitoSimpleMessageJob::class);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendAvitoSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->chat_id);
        // Avito receives plain text — HTML tags stripped from the AI draft.
        $this->assertEquals('Здравствуйте! Спасибо за обращение.', $job->queryParams->text);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'avito',
            'message_type' => 'outgoing',
            'text' => 'Здравствуйте! Спасибо за обращение.',
        ]);

        $this->assertEquals(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_dispatches_simple_email_job_for_email_user_and_persists_plain_text(): void
    {
        $botUser = BotUser::getUserByChatId('ai-answer@example.com', 'email');

        $htmlText = '<b>Здравствуйте!</b> <i>Спасибо за обращение.</i>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $htmlText);

        $this->assertTrue($result);

        Queue::assertPushed(SendEmailMessageJob::class);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendEmailMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals($botUser->chat_id, $job->queryParams->to);
        // Email receives plain text — HTML tags stripped from the AI draft.
        $this->assertEquals('Здравствуйте! Спасибо за обращение.', $job->queryParams->text);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'email',
            'message_type' => 'outgoing',
            'text' => 'Здравствуйте! Спасибо за обращение.',
        ]);

        $this->assertEquals(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_no_duplicate_messages_row_on_repeated_calls(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'vk');
        $botUser->save();

        (new DeliverAiAnswerToUser())->execute($botUser, 'First message');
        (new DeliverAiAnswerToUser())->execute($botUser, 'Second message');

        $this->assertEquals(2, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_returns_false_for_unsupported_platform_and_dispatches_nothing(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'unknown_platform');
        $botUser->topic_id = 400;
        $botUser->save();

        $result = (new DeliverAiAnswerToUser())->execute($botUser, 'some text');

        $this->assertFalse($result);

        // getUserByChatId() dispatches EnrichBotUserProfileJob — that's fine.
        // Verify no platform send jobs were dispatched for unsupported platforms.
        Queue::assertNotPushed(SendTelegramSimpleQueryJob::class);
        Queue::assertNotPushed(SendTelegramMessageJob::class);
        Queue::assertNotPushed(SendVkSimpleMessageJob::class);
        Queue::assertNotPushed(SendVkMessageJob::class);
        Queue::assertNotPushed(SendMaxSimpleMessageJob::class);
        Queue::assertNotPushed(SendMaxMessageJob::class);
        Queue::assertNotPushed(SendAvitoSimpleMessageJob::class);
        Queue::assertNotPushed(SendEmailMessageJob::class);

        // No messages row created for unsupported platform.
        $this->assertEquals(0, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_message_persisted_even_when_send_would_fail(): void
    {
        // The simple job is dispatched to the queue but never actually executed here
        // (Queue::fake()), simulating a scenario where the underlying API call would
        // fail at runtime. The messages row must already exist before the job runs.
        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->save();

        (new DeliverAiAnswerToUser())->execute($botUser, 'Hello!');

        // Row exists regardless of job execution outcome.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Hello!',
        ]);
    }
}
