<?php

namespace Tests\Unit\Modules\Ai\Actions;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Actions\DeliverAiAnswerToUser;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Vk\Jobs\SendVkMessageJob;
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

    public function test_dispatches_telegram_job_for_telegram_user_and_keeps_html(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'telegram');
        $botUser->topic_id = 123;
        $botUser->save();

        $text = '<b>Здравствуйте!</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $text);

        $this->assertTrue($result);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals($botUser->id, $job->botUserId);
        $this->assertEquals($botUser->chat_id, $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($text, $job->queryParams->text);
        $this->assertEquals('html', $job->queryParams->parse_mode);
    }

    public function test_dispatches_vk_job_for_vk_user_and_strips_html(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'vk');
        $botUser->topic_id = 200;
        $botUser->save();

        $text = '<b>Здравствуйте!</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $text);

        $this->assertTrue($result);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals($botUser->id, $job->botUserId);
        $this->assertEquals('messages.send', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->peer_id);
        $this->assertEquals('Здравствуйте!', $job->queryParams->message);
    }

    public function test_dispatches_max_job_for_max_user_and_strips_html(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'max');
        $botUser->topic_id = 300;
        $botUser->save();

        $text = '<b>Здравствуйте</b>';

        $result = (new DeliverAiAnswerToUser())->execute($botUser, $text);

        $this->assertTrue($result);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals($botUser->id, $job->botUserId);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->user_id);
        $this->assertEquals('Здравствуйте', $job->queryParams->text);
    }

    public function test_returns_false_for_unsupported_platform_and_dispatches_nothing(): void
    {
        $botUser = BotUser::getUserByChatId(time(), 'unknown_platform');
        $botUser->topic_id = 400;
        $botUser->save();

        $result = (new DeliverAiAnswerToUser())->execute($botUser, 'some text');

        $this->assertFalse($result);

        Queue::assertNothingPushed();
    }
}
