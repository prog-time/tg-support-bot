<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\EditAiMessage;
use App\Actions\Telegram\SendAiAnswerMessage;
use App\Helpers\AiHelper;
use App\Models\BotUser;

class EditAiMessageTest extends AiActionTest
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();
    }

    public function test_edit_ai_message(): void
    {
        // Отправка команды /ai_generate
        $testMessage = 'Напиши приветствие';
        $resultGenerateMessage = $this->sendGenerateAiMessage($testMessage);

        $dto = $this->createDto($resultGenerateMessage->message_id);
        $resultAiQuery = (new SendAiAnswerMessage())->execute($dto);

        $this->createAiMessage($this->botUser->id, $dto->messageId);
        // -------

        // Создание сообщения с командой на редактирование
        $editMessage = 'Новый ответ от AI';
        $usernameBot = config('traffic_source.settings.telegram_ai.username');
        $newMessage = "@{$usernameBot} ai_message_edit_{$resultAiQuery->message_id} \n {$editMessage}";

        $resultGenerateMessage = $this->sendGenerateAiMessage($newMessage);

        $dto = $this->createDto($resultGenerateMessage->message_id, null, $newMessage);
        // -------

        // Редактирования сообщения
        $resultAiQuery = (new EditAiMessage())->execute($dto);
        // -------

        $this->assertTrue($resultAiQuery->ok);
        $this->assertEquals($resultAiQuery->response_code, 200);

        $this->assertTrue(str_contains($resultAiQuery->text, $editMessage));

        //        $this->assertEquals(
        //            $resultAiQuery->rawData['result']['reply_markup'],
        //            AiHelper::preparedAiReplyMarkup($resultAiQuery->message_id, $resultAiQuery->text)
        //        );
    }
}
