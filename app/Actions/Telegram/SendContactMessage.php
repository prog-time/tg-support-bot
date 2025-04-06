<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

/**
 * Send contact data
 */
class SendContactMessage
{
    /**
     * Sending contact info
     * @param BotUser $botUser
     * @return void
     */
    private function execute(BotUser $botUser): void
    {
        $textMessage = $this->createContactMessage($botUser->chat_id);
        $dataQuery = [
            'chat_id' => env('TELEGRAM_GROUP_ID'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $textMessage,
            'parse_mode' => 'html',
        ];
        TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
    }

    /**
     * Getting chat info out Telegram
     * @param TelegramUpdateDto $update
     * @return void
     */
    public function executeByTgUpdate(TelegramUpdateDto $update): void
    {
        $botUser = BotUser::getUserData($update, 'telegram');
        $this->execute($botUser);
    }

    /**
     * @param BotUser $botUser
     * @return void
     */
    public function executeByBotUser(BotUser $botUser): void
    {
        $this->execute($botUser);
    }

    /**
     * Create contact message
     * @param int $chatId
     * @return string
     */
    private function createContactMessage(int $chatId): string
    {
        try {
            $chat = GetChat::execute($chatId);
            $chatData = $chat->rawData;

            if (empty($chatData)) {
                throw new Exception('Чат не найден!');
            }

            $textMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
            $textMessage .= "Источник: Telegram \n";
            $textMessage .= "ID: {$chatId} \n";

            if (!empty($chatData['result']['username'])) {
                $link = "https://telegram.me/{$chatData['result']['username']}";
                $textMessage .= "Ссылка: {$link} \n";
            }

            return $textMessage;
        } catch (\Exception $exception) {
            return "";
        }
    }

}
