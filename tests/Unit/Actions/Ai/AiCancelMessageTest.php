<?php

namespace Tests\Unit\Actions\Ai;

use App\Actions\Ai\AiCancelMessage;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdate_AiAcceptDtoMock;
use Tests\TestCase;

class AiCancelMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        config(['traffic_source.settings.telegram_ai.token' => 'test_token']);

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
    }

    public function test_cancel_ai_message(): void
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

        $dataParams = TelegramUpdate_AiAcceptDtoMock::getDtoParams();
        $dataParams['callback_query']['data'] = 'ai_message_cancel_' . $messageAiData->message_id;
        $dto = TelegramUpdate_AiAcceptDtoMock::getDto($dataParams);

        (new AiCancelMessage())->execute($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $firstJob->queryParams->chat_id);
        $this->assertEquals('deleteMessage', $firstJob->queryParams->methodQuery);
    }
}
