<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;

/**
 * Отправка контактной информации
 */
class SendContactMessage
{
    /**
     * Отправка контактной информации
     *
     * @param BotUser $botUser
     *
     * @return TelegramAnswerDto
     */
    private function execute(BotUser $botUser): TelegramAnswerDto
    {
        return TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $this->createContactMessage($botUser),
            'parse_mode' => 'html',
        ]);
    }

    /**
     * Подготовка сообщения для отправки
     *
     * @param int $chatId
     *
     * @return TelegramAnswerDto
     */
    public function executeByChatId(int $chatId): TelegramAnswerDto
    {
        $botUser = BotUser::where('chat_id', $chatId)->first();
        return $this->execute($botUser);
    }

    /**
     * @param BotUser $botUser
     *
     * @return TelegramAnswerDto
     */
    public function executeByBotUser(BotUser $botUser): TelegramAnswerDto
    {
        return $this->execute($botUser);
    }

    /**
     * Создание сообщения для отправки
     *
     * @param BotUser $botUser
     *
     * @return string
     */
    public function createContactMessage(BotUser $botUser): string
    {
        try {
            $textMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
            $textMessage .= "Источник: {$botUser->platform} \n";
            $textMessage .= "ID: {$botUser->chat_id} \n";

            if ($botUser->platform === 'telegram') {
                $chat = GetChat::execute($botUser->chat_id);
                $chatData = $chat->rawData;
                if (!empty($chatData['result']['username'])) {
                    $link = "https://telegram.me/{$chatData['result']['username']}";
                    $textMessage .= "Ссылка: {$link} \n";
                }
            }
            return $textMessage;
        } catch (\Exception $e) {
            return '';
        }
    }
}
