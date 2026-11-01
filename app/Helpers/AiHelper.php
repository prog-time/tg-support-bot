<?php

namespace App\Helpers;

class AiHelper
{
    public const AI_LABEL = '🤖 AI:';

    /**
     * Build the plain-text group message for an AI answer. AI text is often
     * not valid Telegram HTML, so markup is stripped and no parse_mode is used.
     *
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

        $textMessage .= self::AI_LABEL . "\n" . self::toPlainText($aiText);

        return $textMessage;
    }

    /**
     * Strip Telegram HTML markup from an AI answer.
     *
     * @param string $text
     *
     * @return string
     */
    public static function toPlainText(string $text): string
    {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
