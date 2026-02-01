<?php

namespace App\Enums;

enum TelegramError: string
{
    case MESSAGE_NOT_MODIFIED = 'Bad Request: message is not modified';
    case MESSAGE_TO_EDIT_NOT_FOUND = 'Bad Request: message to edit not found';
    case MESSAGE_TEXT_IS_EMPTY = 'Bad Request: message text is empty';

    case TOPIC_NOT_FOUND = 'Bad Request: message thread not found';
    case TOPIC_DELETED = 'Bad Request: TOPIC_DELETED';
    case TOPIC_ID_INVALID = 'Bad Request: TOPIC_ID_INVALID';

    case CHAT_NOT_FOUND = 'Bad Request: chat not found';

    case MARKDOWN_ERROR = "Bad Request: can't parse entities";
    case BAD_REQUEST = 'Bad Request';
    case UNAUTHORIZED = 'Unauthorized';
    case FORBIDDEN = 'Forbidden';
    case NOT_FOUND = 'Not Found';
    case CONFLICT = 'Conflict';
    case TOO_MANY_REQUESTS = 'Too Many Requests';
    case INTERNAL_SERVER_ERROR = 'Internal Server Error';

    case DOCUMENT_NOT_FOUND = 'Bad Request: there is no document in the request';

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BAD_REQUEST => 'Bad request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Access denied',
            self::NOT_FOUND => 'Not found',
            self::CONFLICT => 'Conflict',
            self::TOO_MANY_REQUESTS => 'Too many requests',
            self::INTERNAL_SERVER_ERROR => 'Internal server error',

            self::MESSAGE_NOT_MODIFIED => 'Message not modified',
            self::MESSAGE_TO_EDIT_NOT_FOUND => 'Message to edit not found',
            self::MESSAGE_TEXT_IS_EMPTY => 'Message is empty',

            self::DOCUMENT_NOT_FOUND => 'Document not found',

            self::CHAT_NOT_FOUND => 'Chat not found',

            default => 'Unknown Telegram API error'
        };
    }

    /**
     * @param string $description
     * @return self|null
     */
    public static function fromResponse(string $description): ?self
    {
        foreach (self::cases() as $error) {
            if ($description === $error->value) {
                return $error;
            }
        }

        foreach (self::cases() as $error) {
            if (str_contains($description, $error->value)) {
                return $error;
            }
        }

        return null;
    }
}
