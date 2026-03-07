<?php

namespace Tests\Unit\Modules\Telegram\Services;

use App\Models\BotUser;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Telegram\Services\TelegramGroupInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TelegramGroupInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private TelegramGroupInterface $service;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->service = app(TelegramGroupInterface::class);
        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_notify_incoming_message_dispatches_send_telegram_message_job(): void
    {
        $dto = TelegramUpdateDtoMock::getDto();

        $this->service->notifyIncomingMessage($this->botUser, $dto);

        Queue::assertPushed(SendTelegramMessageJob::class);
    }

    public function test_create_conversation_dispatches_topic_create_job(): void
    {
        $this->service->createConversation($this->botUser->id);

        Queue::assertPushed(TopicCreateJob::class);
    }
}
