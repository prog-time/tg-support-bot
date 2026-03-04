<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeleteForumTopicTest extends TestCase
{
    public int $chatId;

    public function setUp(): void
    {
        parent::setUp();

        $this->chatId = time();
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
        app(DeleteForumTopic::class)->execute($botUser);

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
