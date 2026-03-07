<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\CloseTopic;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CloseTopicTest extends TestCase
{
    use RefreshDatabase;

    private int $groupId;

    public function setUp(): void
    {
        parent::setUp();

        $this->groupId = time();
        config(['traffic_source.settings.telegram.group_id' => $this->groupId]);

        Queue::fake();
    }

    public function test_close_topic_other_platform(): void
    {
        $chatId = time();
        $botUser = BotUser::getUserByChatId($chatId, 'test');

        app(CloseTopic::class)->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('editForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);

        $job = $pushed[1]['job'];
        $this->assertEquals('closeForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);
    }

    public function test_close_topic_telegram(): void
    {
        $chatId = time();
        $botUser = BotUser::getUserByChatId($chatId, 'telegram');

        app(CloseTopic::class)->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(3, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->chat_id);

        $job = $pushed[1]['job'];
        $this->assertEquals('editForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);

        $job = $pushed[2]['job'];
        $this->assertEquals('closeForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);
    }

    public function test_close_topic_vk(): void
    {
        $chatId = time();
        $botUser = BotUser::getUserByChatId($chatId, 'vk');

        app(CloseTopic::class)->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('messages.send', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->peer_id);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $job = $pushed[0]['job'];
        $this->assertEquals('editForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);

        $job = $pushed[1]['job'];
        $this->assertEquals('closeForumTopic', $job->queryParams->methodQuery);
        $this->assertEquals($this->groupId, $job->queryParams->chat_id);
        $this->assertEquals($botUser->topic_id, $job->queryParams->message_thread_id);
    }
}
