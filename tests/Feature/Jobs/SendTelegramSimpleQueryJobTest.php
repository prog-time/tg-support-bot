<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class SendTelegramSimpleQueryJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    private int $groupId;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->dto = TelegramUpdateDtoMock::getDto();
        $this->botUser = BotUser::getOrCreateByTelegramUpdate($this->dto);

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->groupId = time();

        $this->botUser->refresh();
    }

    public function test_edit_forum_topic_outgoing(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/editForumTopic' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $job = new SendTelegramSimpleQueryJob(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => $this->groupId,
            'message_thread_id' => $this->botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.outgoing'),
        ]));

        $job->handle();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'editForumTopic')) {
                return false;
            }
            $data = $request->data();

            return ($data['chat_id'] ?? null) == $this->groupId
                && ($data['message_thread_id'] ?? null) == $this->botUser->topic_id
                && ($data['icon_custom_emoji_id'] ?? null) == __('icons.outgoing');
        });

        $job = new SendTelegramSimpleQueryJob(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.incoming'),
        ]));

        $job->handle();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'editForumTopic')) {
                return false;
            }
            $data = $request->data();

            return ($data['chat_id'] ?? null) == config('traffic_source.settings.telegram.group_id')
                && ($data['message_thread_id'] ?? null) == $this->botUser->topic_id
                && ($data['icon_custom_emoji_id'] ?? null) == __('icons.incoming');
        });

        $botUser = BotUser::where([
            'chat_id' => time(),
        ])->first();
        if (isset($botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }
    }
}
