<?php

declare(strict_types=1);

namespace App\Services\Button;

use App\DTOs\Button\ButtonDto;
use App\DTOs\Button\ParsedMessageDto;

/**
 * Парсер синтаксиса кнопок из текста сообщения.
 *
 * Синтаксис: [[Текст кнопки|тип:значение]]
 *
 * Примеры:
 * - [[Открыть сайт|url:https://example.com]] - кнопка со ссылкой
 * - [[Вернуться|callback:back]] - inline callback кнопка
 * - [[Поделиться номером|phone]] - кнопка запроса телефона
 * - [[Текст ответа]] - reply keyboard с текстом
 */
class ButtonParser
{
    /**
     * Регулярное выражение для поиска кнопок.
     * Формат: [[текст]] или [[текст|команда]]
     */
    private const BUTTON_PATTERN = "/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/";

    /**
     * Парсит текст сообщения и извлекает кнопки.
     *
     * @param string $message Исходный текст сообщения
     *
     * @return ParsedMessageDto
     */
    public function parse(string $message): ParsedMessageDto
    {
        $buttons = [];
        $currentRow = 0;
        $lastPosition = 0;

        preg_match_all(self::BUTTON_PATTERN, $message, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return new ParsedMessageDto(text: $message, buttons: []);
        }

        foreach ($matches[0] as $index => $match) {
            $fullMatch = $match[0];
            $position = $match[1];
            $text = $matches[1][$index][0];
            $command = $matches[2][$index][0] ?? null;

            // Проверяем, была ли новая строка между кнопками
            if ($index > 0) {
                $textBetween = substr($message, $lastPosition, $position - $lastPosition);
                if (str_contains($textBetween, "\n")) {
                    $currentRow++;
                }
            }

            $buttons[] = ButtonDto::fromParsed(
                text: trim($text),
                command: $command === '' ? null : $command,
                row: $currentRow
            );

            $lastPosition = $position + strlen($fullMatch);
        }

        // Удаляем синтаксис кнопок из текста
        $cleanText = $this->removeButtonSyntax($message);

        return new ParsedMessageDto(
            text: $cleanText,
            buttons: $buttons
        );
    }

    /**
     * Удаляет синтаксис кнопок из текста.
     *
     * @param string $message Исходный текст
     *
     * @return string
     */
    private function removeButtonSyntax(string $message): string
    {
        // Удаляем все кнопки
        $cleanText = preg_replace(self::BUTTON_PATTERN, '', $message);

        // Убираем лишние пустые строки в конце
        $cleanText = rtrim($cleanText ?? '');

        // Убираем множественные пустые строки
        $cleanText = preg_replace('/\n{3,}/', "\n\n", $cleanText);

        return $cleanText ?? '';
    }

    /**
     * Проверяет, содержит ли текст синтаксис кнопок.
     *
     * @param string $message Текст для проверки
     *
     * @return bool
     */
    public function hasButtons(string $message): bool
    {
        return preg_match(self::BUTTON_PATTERN, $message) === 1;
    }
}
