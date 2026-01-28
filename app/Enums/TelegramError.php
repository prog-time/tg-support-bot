<?php

namespace App\Enums;

enum TelegramError: string
{
    // Ошибки сообщений
    case MESSAGE_NOT_MODIFIED = 'Bad Request: message is not modified';
    case MESSAGE_TO_EDIT_NOT_FOUND = 'Bad Request: message to edit not found';
    case MESSAGE_TEXT_IS_EMPTY = 'Bad Request: message text is empty';

    // Ошибки тем
    case TOPIC_NOT_FOUND = 'Bad Request: message thread not found';
    case TOPIC_DELETED = 'Bad Request: TOPIC_DELETED';
    case TOPIC_ID_INVALID = 'Bad Request: TOPIC_ID_INVALID';

    // Ошибки чата
    case CHAT_NOT_FOUND = 'Bad Request: chat not found';

    // Общие HTTP ошибки
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
            self::BAD_REQUEST => 'Неверный запрос',
            self::UNAUTHORIZED => 'Неавторизован',
            self::FORBIDDEN => 'Доступ запрещен',
            self::NOT_FOUND => 'Не найдено',
            self::CONFLICT => 'Конфликт',
            self::TOO_MANY_REQUESTS => 'Слишком много запросов',
            self::INTERNAL_SERVER_ERROR => 'Внутренняя ошибка сервера',

            self::MESSAGE_NOT_MODIFIED => 'Сообщение не изменено',
            self::MESSAGE_TO_EDIT_NOT_FOUND => 'Сообщение для редактирования не найдено',
            self::MESSAGE_TEXT_IS_EMPTY => 'Сообщение пустое',

            self::DOCUMENT_NOT_FOUND => 'Документ не найден',

            self::CHAT_NOT_FOUND => 'Чат не найден',

            default => 'Неизвестная ошибка Telegram API'
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
