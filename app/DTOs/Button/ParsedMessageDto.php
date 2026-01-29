<?php

declare(strict_types=1);

namespace App\DTOs\Button;

/**
 * DTO для распарсенного сообщения с кнопками
 */
readonly class ParsedMessageDto
{
    /**
     * @param string           $text    Текст сообщения без синтаксиса кнопок
     * @param array<ButtonDto> $buttons Массив кнопок
     */
    public function __construct(
        public string $text,
        public array $buttons = [],
    ) {
    }

    /**
     * Проверяет, есть ли кнопки в сообщении.
     *
     * @return bool
     */
    public function hasButtons(): bool
    {
        return count($this->buttons) > 0;
    }

    /**
     * Проверяет, есть ли inline кнопки.
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
     * Проверяет, есть ли reply keyboard кнопки.
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
     * Возвращает только inline кнопки.
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
     * Возвращает только reply keyboard кнопки.
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
     * Возвращает кнопки, сгруппированные по рядам.
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
