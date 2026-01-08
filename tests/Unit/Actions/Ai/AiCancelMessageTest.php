<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiCancelMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdate_AiButtonAction;
use Tests\TestCase;

class AiCancelMessageTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        BotUser::truncate();
        Message::truncate();
        Queue::fake();

        $this->groupId = time();

        config(['traffic_source.settings.telegram_ai.token' => 'test_token']);
        config(['traffic_source.settings.telegram.group_id' => $this->groupId]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_cancel_ai_message(): void
    {
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
        $dataParams['callback_query']['data'] = 'ai_message_cancel_' . $messageAiData->message_id;
        $dataParams['callback_query']['message']['message_thread_id'] = $this->botUser->topic_id;
        $dto = TelegramUpdate_AiButtonAction::getDto($dataParams);

        (new AiCancelMessage())->execute($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals($this->groupId, $firstJob->queryParams->chat_id);
        $this->assertEquals('deleteMessage', $firstJob->queryParams->methodQuery);
    }
}
