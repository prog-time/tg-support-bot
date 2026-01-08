<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiAcceptMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdate_AiButtonAction;
use Tests\TestCase;

class AiAcceptMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        BotUser::truncate();
        Message::truncate();
        Queue::fake();

        config(['traffic_source.settings.telegram_ai.token' => 'test_token']);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_accept_ai_message(): void
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

        $messageAiData = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => $messageData->id,
            'text_ai' => $aiTextMessage,
            'text_manager' => $managerTextMessage,
        ]);

        $dataParams = TelegramUpdate_AiButtonAction::getDtoParams();
        $dataParams['callback_query']['data'] = 'ai_message_send_' . $messageAiData->message_id;
        $dataParams['callback_query']['message']['message_thread_id'] = $this->botUser->topic_id;
        $dto = TelegramUpdate_AiButtonAction::getDto($dataParams);

        (new AiAcceptMessage())->execute($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $firstJob = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $firstJob->queryParams->chat_id);
        $this->assertEquals('editMessageText', $firstJob->queryParams->methodQuery);
        $this->assertEquals($aiTextMessage, $firstJob->queryParams->text);

        $secondJob = $pushed[1]['job'];

        $this->assertEquals($this->botUser->id, $secondJob->botUserId);
        $this->assertEquals($this->botUser->chat_id, $secondJob->queryParams->chat_id);
        $this->assertEquals('sendMessage', $secondJob->queryParams->methodQuery);
        $this->assertEquals($aiTextMessage, $secondJob->queryParams->text);
    }
}
