<?php

namespace Tests\Unit\Modules\Max\Actions;

use App\Models\BotUser;
use App\Modules\Max\Actions\SendBannedMessageMax;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageMaxTest extends TestCase
{
    use RefreshDatabase;

    private int $chatId;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->chatId = time();
        $this->botUser = BotUser::getUserByChatId($this->chatId, 'max');
    }

    public function test_it_dispatches_max_message_job_with_correct_payload(): void
    {
        $botUser = $this->botUser;

        (new SendBannedMessageMax())->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertInstanceOf(MaxTextMessageDto::class, $job->queryParams);

        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->user_id);
    }
}
