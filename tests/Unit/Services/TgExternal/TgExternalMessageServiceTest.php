<?php

namespace Tests\Unit\Services\TgExternal;

use App\Models\BotUser;
use App\Models\Message;
use App\Services\External\ExternalMessageService;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class TgExternalMessageServiceTest extends TestCase
{
    public function test_send_text_message(): void
    {
        Message::truncate();

        $dto = ExternalMessageDtoMock::getDto();
        $botUser = (new BotUser())->getExternalBotUser($dto);

        (new ExternalMessageService($dto))->handleUpdate();

        // Проверяем, что сообщение сохранилось в базе
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'platform' => config('testing.external.source'),
        ]);
    }
}
