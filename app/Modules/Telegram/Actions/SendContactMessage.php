<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;

/**
 * Send contact message
 */
class SendContactMessage
{
    /**
     * Send contact message
     *
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        $queryParams = $this->getQueryParams($botUser);
        SendTelegramSimpleQueryJob::dispatch($queryParams);
    }

    /**
     * @param BotUser $botUser
     *
     * @return TGTextMessageDto
     */
    public function getQueryParams(BotUser $botUser): TGTextMessageDto
    {
        return TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $this->createContactMessage($botUser->chat_id, $botUser->platform),
            'parse_mode' => 'html',
            'reply_markup' => [
                'inline_keyboard' => $this->getKeyboard($botUser),
            ],
        ]);
    }

    /**
     * Create contact message
     *
     * @param int    $chatId
     * @param string $platform
     *
     * @return string
     */
    public function createContactMessage(int $chatId, string $platform): string
    {
        try {
            $textMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
            $textMessage .= "Источник: {$platform} \n";
            $textMessage .= "ID: <code>{$chatId}</code> \n";

            if ($platform === 'telegram') {
                $chat = GetChat::execute($chatId);
                $chatData = $chat->rawData;
                if (!empty($chatData['result']['username'])) {
                    $link = "https://telegram.me/{$chatData['result']['username']}";
                    $textMessage .= "Пользователь: <code>{$chatData['result']['username']}</code> \n";
                    $textMessage .= "Ссылка: {$link} \n";
                }
            }
            return $textMessage;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param BotUser $botUser
     *
     * @return array
     */
    public function getKeyboard(BotUser $botUser): array
    {
        if ($botUser->isBanned()) {
            $banButton = [
                'text' => __('messages.but_ban_user_false'),
                'callback_data' => 'topic_user_ban_false',
            ];
        } else {
            $banButton = [
                'text' => __('messages.but_ban_user_true'),
                'callback_data' => 'topic_user_ban_true',
            ];
        }

        return [
            [
                $banButton,
            ],
            [
                [
                    'text' => __('messages.but_close_topic'),
                    'callback_data' => 'close_topic',
                ],
            ],
        ];
    }
}
