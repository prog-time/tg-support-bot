<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\SendStartMessage;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
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
        $action = app(SendStartMessage::class);
        $action->handle($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals($botUser->id, $job->botUserId);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }

    public function test_send_start_message_with_keyboard(): void
    {
        $startText = "Welcome!\n[[Button|callback:test]]";

        $this->app->setLocale('ru');
        $this->app['translator']->addLines(['messages.start' => $startText], 'ru');

        $dtoUpdateParams = TelegramUpdateDtoMock::getDtoParams();
        $dtoUpdateParams['message']['text'] = '/start';

        $dto = TelegramUpdateDtoMock::getDto($dtoUpdateParams);
        BotUser::getOrCreateByTelegramUpdate($dto);

        $action = app(SendStartMessage::class);
        $action->handle($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals('Welcome!', $job->queryParams->text);
        $this->assertNotNull($job->queryParams->reply_markup);
        $this->assertArrayHasKey('inline_keyboard', $job->queryParams->reply_markup);
    }
}
