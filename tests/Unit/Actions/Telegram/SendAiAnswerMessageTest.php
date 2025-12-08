<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendAiAnswerMessage;
use App\Jobs\SendMessage\SendAiResponseMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_GroupMock;
use Tests\TestCase;

class SendAiAnswerMessageTest extends TestCase
{
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

    public function test_generate_ai_message(): void
    {
        $dtoParams = TelegramUpdateDto_GroupMock::getDtoParams();
        $dtoParams['message']['text'] = '/ai_generate напиши приветствие';
        $dto = TelegramUpdateDto_GroupMock::getDto($dtoParams);

        (new SendAiAnswerMessage())->execute($dto);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendAiResponseMessageJob::class] ?? [];

        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
        $this->assertEquals($dto->text, $firstJob->updateDto->text);
    }
}
