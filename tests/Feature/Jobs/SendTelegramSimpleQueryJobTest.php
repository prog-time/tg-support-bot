<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendTelegramSimpleQueryJobTest extends TestCase
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
        $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->dto);

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->botUser->refresh();

        sleep(3);
    }

    //    protected function tearDown(): void
    //    {
    //        $botUser = BotUser::where([
    //            'chat_id' => config('testing.tg_private.chat_id'),
    //        ])->first();
    //        if (isset($botUser->topic_id)) {
    //            DeleteForumTopic::execute($this->botUser);
    //        }
    //
    //        parent::tearDown();
    //    }

    public function test_edit_forum_topic_outgoing(): void
    {
        $job = new SendTelegramSimpleQueryJob(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.outgoing'),
        ]));

        $resultQuery = $job->handle();
        $this->assertTrue($resultQuery);

        sleep(3);

        $job = new SendTelegramSimpleQueryJob(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.incoming'),
        ]));

        $resultQuery = $job->handle();
        $this->assertTrue($resultQuery);

        $botUser = BotUser::where([
            'chat_id' => config('testing.tg_private.chat_id'),
        ])->first();
        if (isset($botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }
    }
}
