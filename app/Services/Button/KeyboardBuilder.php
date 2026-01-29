<?php

declare(strict_types=1);

namespace App\Services\Button;

use App\DTOs\Button\ButtonDto;
use App\DTOs\Button\ParsedMessageDto;

/**
 * Строитель клавиатур для Telegram и VK.
 */
class KeyboardBuilder
{
    /**
     * Создает Telegram inline keyboard из распарсенного сообщения.
     *
     * @param ParsedMessageDto $parsedMessage Распарсенное сообщение
     *
     * @return array<string, array<int, array<int, array<string, string>>>>|null
     */
    public function buildTelegramInlineKeyboard(ParsedMessageDto $parsedMessage): ?array
    {
        if (!$parsedMessage->hasInlineButtons()) {
            return null;
        }

        $keyboard = [];
        $buttonsByRows = $this->groupButtonsByRows($parsedMessage->getInlineButtons());

        foreach ($buttonsByRows as $rowButtons) {
            $row = [];
            foreach ($rowButtons as $button) {
                $row[] = $button->toTelegramInline();
            }
            $keyboard[] = $row;
        }

        return ['inline_keyboard' => $keyboard];
    }

    /**
     * Создает Telegram reply keyboard из распарсенного сообщения.
     *
     * @param ParsedMessageDto $parsedMessage   Распарсенное сообщение
     * @param bool             $oneTimeKeyboard Скрыть клавиатуру после использования
     * @param bool             $resizeKeyboard  Подогнать размер клавиатуры
     *
     * @return array<string, mixed>|null
     */
    public function buildTelegramReplyKeyboard(
        ParsedMessageDto $parsedMessage,
        bool $oneTimeKeyboard = true,
        bool $resizeKeyboard = true
    ): ?array {
        if (!$parsedMessage->hasReplyKeyboardButtons()) {
            return null;
        }

        $keyboard = [];
        $buttonsByRows = $this->groupButtonsByRows($parsedMessage->getReplyKeyboardButtons());

        foreach ($buttonsByRows as $rowButtons) {
            $row = [];
            foreach ($rowButtons as $button) {
                $row[] = $button->toTelegramReply();
            }
            $keyboard[] = $row;
        }

        return [
            'keyboard' => $keyboard,
            'one_time_keyboard' => $oneTimeKeyboard,
            'resize_keyboard' => $resizeKeyboard,
        ];
    }

    /**
     * Создает Telegram keyboard (автоматически выбирает inline или reply).
     *
     * @param ParsedMessageDto $parsedMessage Распарсенное сообщение
     *
     * @return array<string, mixed>|null
     */
    public function buildTelegramKeyboard(ParsedMessageDto $parsedMessage): ?array
    {
        if (!$parsedMessage->hasButtons()) {
            return null;
        }

        // Приоритет: inline keyboard
        if ($parsedMessage->hasInlineButtons()) {
            return $this->buildTelegramInlineKeyboard($parsedMessage);
        }

        return $this->buildTelegramReplyKeyboard($parsedMessage);
    }

    /**
     * Создает VK keyboard из распарсенного сообщения.
     *
     * @param ParsedMessageDto $parsedMessage Распарсенное сообщение
     * @param bool             $inline        Inline клавиатура
     * @param bool             $oneTime       Одноразовая клавиатура
     *
     * @return array<string, mixed>|null
     */
    public function buildVkKeyboard(
        ParsedMessageDto $parsedMessage,
        bool $inline = false,
        bool $oneTime = false
    ): ?array {
        if (!$parsedMessage->hasButtons()) {
            return null;
        }

        $buttons = [];
        $buttonsByRows = $this->groupButtonsByRows($parsedMessage->buttons);

        foreach ($buttonsByRows as $rowButtons) {
            $row = [];
            foreach ($rowButtons as $button) {
                $row[] = $button->toVkButton();
            }
            $buttons[] = $row;
        }

        return [
            'inline' => $inline,
            'one_time' => $oneTime,
            'buttons' => $buttons,
        ];
    }

    /**
     * Группирует кнопки по рядам.
     *
     * @param array<ButtonDto> $buttons Массив кнопок
     *
     * @return array<int, array<ButtonDto>>
     */
    private function groupButtonsByRows(array $buttons): array
    {
        $rows = [];
        foreach ($buttons as $button) {
            $rows[$button->row][] = $button;
        }
        ksort($rows);

        return array_values($rows);
    }
}
