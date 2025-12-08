<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Services\External\ExternalMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class ExternalMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public string $source;

    public string $external_id;

    public string $text;

    public BotUser $botUser;

    private ExternalMessageDto $dto;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->text = 'Тестовое сообщение';
        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');

        $this->dto = ExternalMessageDtoMock::getDto();

        $this->botUser = (new BotUser())->getExternalBotUser($this->dto);
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
        (new ExternalMessageService($this->dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendExternalTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $job->botUserId);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery, );
        $this->assertEquals('private', $job->queryParams->typeSource, );
        $this->assertEquals($job->queryParams->message_thread_id, $this->botUser->topic_id);
    }
}
