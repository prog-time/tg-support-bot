<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
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
     * @return void
     */
    private function execute(BotUser $botUser): void
    {
        $textMessage = $this->createContactMessage($botUser);
        $dataQuery = [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $textMessage,
            'parse_mode' => 'html',
        ];
        TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
    }

    /**
     * Подготовка сообщения для отправки
     *
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    public function executeByTgUpdate(TelegramUpdateDto $update): void
    {
        $botUser = BotUser::getTelegramUserData($update);
        $this->execute($botUser);
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function executeByBotUser(BotUser $botUser): void
    {
        $this->execute($botUser);
    }

    /**
     * Создание сообщения для отправки
     *
     * @param BotUser $botUser
     *
     * @return string
     */
    private function createContactMessage(BotUser $botUser): string
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
