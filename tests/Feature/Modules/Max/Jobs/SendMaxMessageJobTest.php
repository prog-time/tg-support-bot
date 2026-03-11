<?php

namespace Tests\Feature\Modules\Max\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\Max\Answer\MaxAnswerDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class SendMaxMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'max');

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->botUser->refresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            app(DeleteForumTopic::class)->execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_retries_on_attachment_not_ready_then_succeeds(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto();
        $answerNotReady = MaxAnswerDtoMock::getDto([
            'response_code' => 500,
            'error_message' => 'Max sendFile failed: {"code":"attachment.not.ready","message":"Key: errors.process.attachment.file.not.processed"}',
        ]);
        $answerOk = MaxAnswerDtoMock::getDto();

        /** @var MaxMethods&\Mockery\MockInterface $mockMaxMethods */
        $mockMaxMethods = \Mockery::mock(MaxMethods::class);
        /** @var \Mockery\Expectation $expectation */
        $expectation = $mockMaxMethods->shouldReceive('sendQuery');
        $expectation->times(3)->andReturn($answerNotReady, $answerNotReady, $answerOk);

        $queryParams = MaxTextMessageDto::from([
            'methodQuery' => 'sendFile',
            'user_id' => $this->botUser->chat_id,
            'file_token' => 'test_token',
        ]);

        $job = new SendMaxMessageJob(
            $this->botUser->id,
            $dto,
            $queryParams,
            $mockMaxMethods
        );

        // Override sleep to avoid real waiting in tests
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
        ]);
    }

    public function test_send_message_for_user(): void
    {
        try {
            $typeMessage = 'outgoing';
            $textMessage = 'Тестовое сообщение';
            $dto = TelegramUpdateDto_VKMock::getDto();
            $answerDto = MaxAnswerDtoMock::getDto();

            /** @var MaxMethods&\Mockery\MockInterface $mockMaxMethods */
            $mockMaxMethods = \Mockery::mock(MaxMethods::class);
            $mockMaxMethods->shouldReceive('sendQuery')->andReturn($answerDto);

            $queryParams = MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => $this->botUser->chat_id,
                'text' => $textMessage,
            ]);

            $job = new SendMaxMessageJob(
                $this->botUser->id,
                $dto,
                $queryParams,
                $mockMaxMethods
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                app(DeleteForumTopic::class)->execute($this->botUser);
            }
        }
    }
}
