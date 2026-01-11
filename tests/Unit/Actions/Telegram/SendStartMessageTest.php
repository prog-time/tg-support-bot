<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendStartMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendStartMessageTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_send_start_message(): void
    {
        $dtoUpdateParams = TelegramUpdateDtoMock::getDtoParams();
        $dtoUpdateParams['message']['text'] = '/start';

        // Вызываем фабрику DTO
        $dto = TelegramUpdateDtoMock::getDto($dtoUpdateParams);
        $botUser = BotUser::getOrCreateByTelegramUpdate($dto);

        // Act
        (new SendStartMessage())->execute($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals($botUser->id, $job->botUserId);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
