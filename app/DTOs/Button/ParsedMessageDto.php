<?php

declare(strict_types=1);

namespace App\DTOs\Button;

/**
 * DTO for parsed message with buttons.
 */
readonly class ParsedMessageDto
{
    /**
     * @param string           $text    Message text without button syntax
     * @param array<ButtonDto> $buttons Array of buttons
     */
    public function __construct(
        public string $text,
        public array $buttons = [],
    ) {
    }

    /**
     * Check if message has buttons.
     *
     * @return bool
     */
    public function hasButtons(): bool
    {
        return count($this->buttons) > 0;
    }

    /**
     * Check if message has inline buttons.
     *
     * @return bool
     */
    public function hasInlineButtons(): bool
    {
        foreach ($this->buttons as $button) {
            if ($button->type->isInline()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message has reply keyboard buttons.
     *
     * @return bool
     */
    public function hasReplyKeyboardButtons(): bool
    {
        foreach ($this->buttons as $button) {
            if ($button->type->isReplyKeyboard()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get only inline buttons.
     *
     * @return array<ButtonDto>
     */
    public function getInlineButtons(): array
    {
        return array_filter(
            $this->buttons,
            fn (ButtonDto $button) => $button->type->isInline()
        );
    }

    /**
     * Get only reply keyboard buttons.
     *
     * @return array<ButtonDto>
     */
    public function getReplyKeyboardButtons(): array
    {
        return array_filter(
            $this->buttons,
            fn (ButtonDto $button) => $button->type->isReplyKeyboard()
        );
    }

    /**
     * Get buttons grouped by rows.
     *
     * @return array<int, array<ButtonDto>>
     */
    public function getButtonsByRows(): array
    {
        $rows = [];
        foreach ($this->buttons as $button) {
            $rows[$button->row][] = $button;
        }
        ksort($rows);

        return $rows;
    }
}
