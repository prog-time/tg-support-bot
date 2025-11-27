<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalEditedMessageServiceTest extends TestCase
{
    public string $source;

    public string $external_id;

    public string $text = 'Тестовое сообщение';

    public int $messageId = 0;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

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

    public function createMessage(): Message
    {
        // Сохраняем сообщения в БД
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => $this->source,
            'from_id' => rand(),
            'to_id' => rand(),
        ];
        $createdMessage = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        $createdMessage->externalMessage()->create([
            'text' => 'Тестовое сообщение',
            'file_id' => null,
        ]);

        return $createdMessage;
    }

    public function test_edit_external_message(): void
    {
        // создание сообщения
        $message = $this->createMessage();
        $dataMessage = $this->getMessageParams();

        // отправляем сообщение
        $dataUpdateMessage = array_merge($dataMessage, [
            'message_id' => $message->from_id,
            'text' => 'Изменил сообщение!',
        ]);
        (new ExternalTrafficService())->update(ExternalMessageDto::from($dataUpdateMessage));

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendExternalTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $job->botUserId);
        $this->assertEquals('editMessageText', $job->queryParams->methodQuery);
        $this->assertEquals('private', $job->queryParams->typeSource);
        $this->assertEquals($job->queryParams->message_thread_id, $this->botUser->topic_id);
    }
}
