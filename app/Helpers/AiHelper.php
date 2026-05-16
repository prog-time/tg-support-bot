<?php

namespace App\Helpers;

class AiHelper
{
    /**
     * @param string $managerText
     * @param string $aiText
     *
     * @return string
     */
    public static function preparedAiAnswer(string $managerText, string $aiText): string
    {
        $textMessage = '';

        if ($managerText !== '') {
            $textMessage .= "📄 Инструкция: \n{$managerText}  \n\n";
        }

        $textMessage .= "🤖 Ответ от AI: \n{$aiText} \n\n";

        return $textMessage;
    }

    /**
     * @param int    $messageId
     * @param string $aiText
     *
     * @return array
     */
    public static function preparedAiReplyMarkup(int $messageId, string $aiText): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Отправить',
                        'callback_data' => "ai_message_send_{$messageId}",
                    ],
                    [
                        'text' => '❌ Отменить',
                        'callback_data' => "ai_message_cancel_{$messageId}",
                    ],
                ],
                [
                    [
                        'text' => '📝 Редактировать ответ',
                        'switch_inline_query_current_chat' => "ai_message_edit_{$messageId} \n\n" . $aiText,
                    ],
                ],
            ],
        ];
    }
}
