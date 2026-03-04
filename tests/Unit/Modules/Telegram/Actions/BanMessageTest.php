<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\BanMessage;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
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
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $firstJob->queryParams->chat_id);
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals(__('messages.ban_bot'), $firstJob->queryParams->text);
    }
}
