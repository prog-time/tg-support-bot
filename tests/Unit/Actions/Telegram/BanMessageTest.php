<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\BanMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\TestCase;

class BanMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
    }

    public function test_send_ban_message_with_correct_text(): void
    {
        $dto = TelegramUpdateDto_GroupMock::getDto();

        BanMessage::execute($this->botUser, $dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $firstJob->queryParams->chat_id);
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals(__('messages.ban_bot'), $firstJob->queryParams->text);
    }
}
