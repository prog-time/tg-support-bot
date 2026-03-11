<?php

namespace Tests\Feature\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Max\MaxUpdateDtoMock;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\TestCase;

class SendMaxTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private MaxUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = MaxUpdateDtoMock::getDto();
        $this->botUser = BotUser::getUserByChatId($this->dto->from_id, 'max');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_send_message_for_user(): void
    {
        try {
            $typeMessage = 'incoming';
            $textMessage = 'Тестовое сообщение';
            $dtoParams = TelegramAnswerDtoMock::getDtoParams();

            $dtoParams['result']['text'] = $textMessage;
            $dto = TelegramAnswerDtoMock::getDto($dtoParams);

            /** @var TelegramMethods&\Mockery\MockInterface $mockTelegramMethods */
            $mockTelegramMethods = \Mockery::mock(TelegramMethods::class);
            $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($dto);

            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->botUser->chat_id,
                'text' => $textMessage,
            ]);

            $job = new SendMaxTelegramMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $mockTelegramMethods
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                app(DeleteForumTopic::class)->execute($this->botUser);
            }
        }
    }
}
