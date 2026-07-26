<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\BanMessage;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\TestCase;

class BanMessageTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
    }

    public function test_send_ban_message_with_correct_text(): void
    {
        $dto = TelegramUpdateDto_GroupMock::getDto();

        app(BanMessage::class)->execute($this->botUser->id, $dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals('-100000000000', $firstJob->queryParams->chat_id);
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals(__('messages.ban_bot'), $firstJob->queryParams->text);
    }

    /**
     * issue #114 — when the user's topic already exists, blocking the bot
     * must flip its icon to the "done" checkmark so managers see it without
     * opening the topic.
     */
    public function test_flips_topic_icon_when_topic_exists(): void
    {
        $this->botUser->update(['topic_id' => 777]);
        $dto = TelegramUpdateDto_GroupMock::getDto();

        app(BanMessage::class)->execute($this->botUser->id, $dto);

        Queue::assertPushed(SendTelegramSimpleQueryJob::class, function ($job) {
            return $job->queryParams->methodQuery === 'editForumTopic'
                && $job->queryParams->chat_id === '-100000000000'
                && $job->queryParams->message_thread_id === 777
                && $job->queryParams->icon_custom_emoji_id === __('icons.blocked');
        });
    }

    /**
     * No existing topic means SendTelegramMessageJob will create one (via
     * TopicCreateJob) with the default "incoming" icon — there's nothing to
     * edit yet, so no editForumTopic call should be dispatched here.
     */
    public function test_skips_icon_update_when_topic_does_not_exist_yet(): void
    {
        $this->assertNull($this->botUser->topic_id);
        $dto = TelegramUpdateDto_GroupMock::getDto();

        app(BanMessage::class)->execute($this->botUser->id, $dto);

        Queue::assertNotPushed(SendTelegramSimpleQueryJob::class);
    }
}
