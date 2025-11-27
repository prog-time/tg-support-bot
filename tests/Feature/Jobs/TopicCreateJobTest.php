<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TopicCreateJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->dto = TelegramUpdateDtoMock::getDto();
        $this->botUser = BotUser::getTelegramUserData($this->dto);
    }

    protected function tearDown(): void
    {
        $botUser = BotUser::where([
            'chat_id' => config('testing.tg_private.chat_id'),
        ])->first();
        if (isset($botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_success_send_creates_message_record(): void
    {
        $job = new TopicCreateJob($this->botUser->id);
        $job->handle();
    }
}
