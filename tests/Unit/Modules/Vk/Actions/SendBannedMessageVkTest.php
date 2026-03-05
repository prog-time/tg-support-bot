<?php

namespace Tests\Unit\Modules\Vk\Actions;

use App\Models\BotUser;
use App\Modules\Vk\Actions\SendBannedMessageVk;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageVkTest extends TestCase
{
    use RefreshDatabase;

    private int $chatId;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->chatId = time();
        $this->botUser = BotUser::getUserByChatId($this->chatId, 'vk');
    }

    public function test_it_dispatches_vk_message_job_with_correct_payload(): void
    {
        $botUser = $this->botUser;

        (new SendBannedMessageVk())->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertInstanceOf(VkTextMessageDto::class, $job->queryParams);

        $this->assertEquals('messages.send', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->peer_id);
    }
}
