<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\DeleteForumTopic;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use Tests\TestCase;

class DeleteForumTopicTest extends TestCase
{
    public int $chatId;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->chatId = config('testing.tg_private.chat_id');

        $this->botUser = BotUser::getUserByChatId($this->chatId, 'telegram');
    }

    public function test_get_chat(): void
    {
        $resultCreateTopic = TelegramMethods::sendQueryTelegram('createForumTopic', [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'name' => 'Тестовый топик',
            'icon_custom_emoji_id' => __('icons.incoming'),
        ]);

        $this->assertTrue($resultCreateTopic->ok);
        $this->assertNotEmpty($resultCreateTopic->message_thread_id);

        DeleteForumTopic::execute($this->botUser);
    }
}
