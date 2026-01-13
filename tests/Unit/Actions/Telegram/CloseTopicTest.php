<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\CloseTopic;
use App\Jobs\SendMessage\SendVkSimpleMessageJob;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
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

        (new CloseTopic())->execute($botUser);

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

        (new CloseTopic())->execute($botUser);

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

        (new CloseTopic())->execute($botUser);

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
