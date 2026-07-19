<?php

namespace Tests\Unit\Modules\Avito\Actions;

use App\Models\BotUser;
use App\Modules\Avito\Actions\SendStartMessageAvito;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendStartMessageAvitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_avito_simple_message_job_with_correct_payload(): void
    {
        Queue::fake();

        $botUser = BotUser::getUserByChatId('u2i-xyz789', 'avito');

        (new SendStartMessageAvito())->execute($botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendAvitoSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertInstanceOf(AvitoTextMessageDto::class, $job->queryParams);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
        $this->assertEquals($botUser->chat_id, $job->queryParams->chat_id);
    }
}
