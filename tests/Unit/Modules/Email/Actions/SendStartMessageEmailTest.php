<?php

namespace Tests\Unit\Modules\Email\Actions;

use App\Models\BotUser;
use App\Modules\Email\Actions\SendStartMessageEmail;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendStartMessageEmailTest extends TestCase
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

        $botUser = BotUser::getUserByChatId('newcomer@example.com', 'email');

        app(SendStartMessageEmail::class)->execute($botUser);

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job) use ($botUser): bool {
            return $job->queryParams instanceof EmailMessageDto
                && $job->queryParams->to === $botUser->chat_id;
        });
    }
}
