<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\DeleteForumTopic;
use App\Models\BotUser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeleteForumTopicTest extends TestCase
{
    public int $chatId;

    public function setUp(): void
    {
        parent::setUp();

        $this->chatId = config('testing.tg_private.chat_id');
    }

    public function test_it_calls_sendQueryTelegram_with_correct_parameters(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        // Подготовка данных
        $botUser = new BotUser();
        $botUser->topic_id = 123;

        // Act — вызываем действие
        DeleteForumTopic::execute($botUser);

        // Assert
        $sentRequests = Http::recorded();
        $this->assertCount(1, $sentRequests);

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $sentRequests[0][0];

        $this->assertStringContainsString('deleteForumTopic', $request->url());
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $request['chat_id']);
        $this->assertEquals($botUser->topic_id, $request['message_thread_id']);
    }
}
