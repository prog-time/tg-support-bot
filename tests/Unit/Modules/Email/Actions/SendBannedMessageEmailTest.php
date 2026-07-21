<?php

namespace Tests\Unit\Modules\Email\Actions;

use App\Models\BotUser;
use App\Modules\Email\Actions\SendBannedMessageEmail;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_dispatches_email_message_job_with_correct_payload(): void
    {
        Queue::fake();

        $botUser = BotUser::getUserByChatId('banned@example.com', 'email');

        app(SendBannedMessageEmail::class)->execute($botUser);

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job) use ($botUser): bool {
            return $job->queryParams instanceof EmailMessageDto
                && $job->queryParams->to === $botUser->chat_id
                && $job->queryParams->subject === 'Поддержка';
        });
    }

    public function test_uses_remembered_thread_subject_when_available(): void
    {
        Queue::fake();

        $botUser = BotUser::getUserByChatId('banned-thread@example.com', 'email');
        app(\App\Modules\Email\Services\EmailThreadStore::class)->remember($botUser->id, 'orig-id@example.com', 'Order issue');

        app(SendBannedMessageEmail::class)->execute($botUser);

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job): bool {
            return $job->queryParams->subject === 'Re: Order issue'
                && $job->queryParams->inReplyTo === 'orig-id@example.com';
        });
    }
}
