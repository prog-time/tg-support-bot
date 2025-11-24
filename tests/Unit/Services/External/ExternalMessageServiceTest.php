<?php

namespace Tests\Unit\Services\External;

use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Services\External\ExternalMessageService;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;
use Tests\Traits\QueueJobTestTrait;

class ExternalMessageServiceTest extends TestCase
{
    use QueueJobTestTrait;

    public string $source;

    public string $external_id;

    public string $text;

    public BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->text = 'Тестовое сообщение';
        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');

        $externalUser = ExternalUser::firstOrCreate([
            'external_id' => $this->external_id,
            'source' => $this->source,
        ]);

        $this->botUser = BotUser::firstOrCreate([
            'chat_id' => $externalUser->id,
            'platform' => $this->source,
        ]);
    }

    protected function getMessageParams(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => $this->text,
        ];
    }

    public function test_send_message(): void
    {
        // отправляем сообщение
        $externalDto = ExternalMessageDtoMock::getDto();

        (new ExternalMessageService($externalDto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendExternalTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $job->botUser->id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery, );
        $this->assertEquals('private', $job->queryParams->typeSource, );
        $this->assertEquals($job->queryParams->message_thread_id, $this->botUser->topic_id);
    }
}
