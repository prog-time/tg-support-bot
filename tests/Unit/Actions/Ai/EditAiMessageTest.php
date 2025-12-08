<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\EditAiMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdate_AiButtonAction;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\TestCase;

class EditAiMessageTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        config(['traffic_source.settings.telegram_ai.token' => 'test_token']);

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_edit_ai_message(): void
    {
        config(['traffic_source.settings.telegram_ai.token' => 'test_token']);
        $aiTextMessage = 'Тестовое сообщение от AI';
        $managerTextMessage = 'Сообщение от менеджера';

        $messageData = Message::create([
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'telegram',
            'from_id' => time(),
            'to_id' => time(),
        ]);

        AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => $messageData->id,
            'text_ai' => $aiTextMessage,
            'text_manager' => $managerTextMessage,
        ]);

        // Создание сообщения с командой на редактирование
        $editMessage = 'Новый ответ от AI';
        $usernameBot = config('traffic_source.settings.telegram_ai.username');
        $newMessage = "@{$usernameBot} ai_message_edit_{$messageData->id} \n {$editMessage}";

        $dataParams = TelegramUpdateDto_GroupMock::getDtoParams();
        $dataParams['message']['text'] = $newMessage;
        $dto = TelegramUpdate_AiButtonAction::getDto($dataParams);
        // -------

        // Редактирования сообщения
        (new EditAiMessage())->execute($dto);
        // -------

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $firstJob->queryParams->chat_id);
        $this->assertEquals('editMessageText', $firstJob->queryParams->methodQuery);

        $secondJob = $pushed[1]['job'];
        $this->assertEquals($this->botUser->id, $secondJob->botUserId);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $secondJob->queryParams->chat_id);
        $this->assertEquals('deleteMessage', $secondJob->queryParams->methodQuery);
    }
}
