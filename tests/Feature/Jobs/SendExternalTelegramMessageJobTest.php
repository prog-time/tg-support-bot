<?php

namespace Tests\Feature\Jobs;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class SendExternalTelegramMessageJobTest extends TestCase
{
    public function test_send_message_for_group(): void
    {
        Message::truncate();

        $typeMessage = 'incoming';

        $dto = ExternalMessageDtoMock::getDto();

        $botUser = (new BotUser())->getExternalBotUser($dto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('testing.tg_group.chat_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => '👋 Тест из SendExternalTelegramMessageJob @ ' . now(),
        ]);

        $job = new SendExternalTelegramMessageJob($botUser, $dto, $queryParams, $typeMessage);
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => $typeMessage,
            'platform' => $dto->source,
        ]);
    }
}
