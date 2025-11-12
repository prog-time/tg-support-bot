<?php

namespace Tests\Feature\Jobs;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendTelegramMessageJobTest extends TestCase
{
    public function test_send_message_for_user(): void
    {
        Message::truncate();

        $typeMessage = 'outgoing';

        $dto = TelegramUpdateDtoMock::getDto();

        $botUser = BotUser::getTelegramUserData($dto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => '👋 Тест из SendTelegramMessageJob @ ' . now(),
        ]);

        $job = new SendTelegramMessageJob($botUser, $dto, $queryParams, $typeMessage);
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'telegram',
        ]);
    }

    public function test_send_message_for_group(): void
    {
        Message::truncate();

        $typeMessage = 'incoming';

        $dto = TelegramUpdateDtoMock::getDto();

        $botUser = BotUser::getTelegramUserData($dto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('testing.tg_group.chat_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => '👋 Тест из SendTelegramMessageJob @ ' . now(),
        ]);

        $job = new SendTelegramMessageJob($botUser, $dto, $queryParams, $typeMessage);
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'telegram',
        ]);
    }

    public function test_send_message_for_group_topic_not_found(): void
    {
        Message::truncate();

        $typeMessage = 'incoming';

        $dto = TelegramUpdateDtoMock::getDto();

        $botUser = BotUser::getTelegramUserData($dto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('testing.tg_group.chat_id'),
            'text' => '👋 Тест из SendTelegramMessageJob @ ' . now(),
        ]);

        $job = new SendTelegramMessageJob($botUser, $dto, $queryParams, $typeMessage);
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => $typeMessage,
            'platform' => 'telegram',
        ]);
    }
}
