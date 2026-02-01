<?php

declare(strict_types=1);

namespace App\Services\Button;

use App\DTOs\Button\ButtonDto;
use App\DTOs\Button\ParsedMessageDto;

/**
 * Keyboard builder for Telegram and VK.
 */
class KeyboardBuilder
{
    /**
     * Create Telegram inline keyboard from parsed message.
     *
     * @param ParsedMessageDto $parsedMessage Parsed message
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
     * Create Telegram reply keyboard from parsed message.
     *
     * @param ParsedMessageDto $parsedMessage   Parsed message
     * @param bool             $oneTimeKeyboard Hide keyboard after use
     * @param bool             $resizeKeyboard  Resize keyboard to fit
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
     * Create Telegram keyboard (automatically selects inline or reply).
     *
     * @param ParsedMessageDto $parsedMessage Parsed message
     *
     * @return array<string, mixed>|null
     */
    public function buildTelegramKeyboard(ParsedMessageDto $parsedMessage): ?array
    {
        if (!$parsedMessage->hasButtons()) {
            return null;
        }

        if ($parsedMessage->hasInlineButtons()) {
            return $this->buildTelegramInlineKeyboard($parsedMessage);
        }

        return $this->buildTelegramReplyKeyboard($parsedMessage);
    }

    /**
     * Create VK keyboard from parsed message.
     *
     * @param ParsedMessageDto $parsedMessage Parsed message
     * @param bool             $inline        Inline keyboard
     * @param bool             $oneTime       One-time keyboard
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
     * Group buttons by rows.
     *
     * @param array<ButtonDto> $buttons Array of buttons
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
