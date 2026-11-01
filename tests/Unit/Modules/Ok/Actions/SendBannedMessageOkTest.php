<?php

namespace Tests\Unit\Modules\Ok\Actions;

use App\Models\BotUser;
use App\Modules\Ok\Actions\SendBannedMessageOk;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageOkTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_the_notice_to_the_chat(): void
    {
        Queue::fake();
        $botUser = BotUser::getUserByChatId('chat:C1', 'ok');

        (new SendBannedMessageOk())->execute($botUser);

        Queue::assertPushed(SendOkSimpleMessageJob::class, fn ($job) => $job->queryParams->chat_id === 'chat:C1'
            && $job->queryParams->text === __('messages.ban_user'));
    }
}
