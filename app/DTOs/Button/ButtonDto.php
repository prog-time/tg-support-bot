<?php

declare(strict_types=1);

namespace App\DTOs\Button;

use App\Enums\ButtonType;

/**
 * DTO for message button.
 */
readonly class ButtonDto
{
    public function __construct(
        public string $text,
        public ButtonType $type,
        public ?string $value = null,
        public int $row = 0,
    ) {
    }

    /**
     * Create button from parsed syntax.
     *
     * @param string      $text    Button text
     * @param string|null $command Command (callback:value, url:link, phone, or null)
     * @param int         $row     Button row number
     *
     * @return self
     */
    public static function fromParsed(string $text, ?string $command, int $row = 0): self
    {
        $type = ButtonType::fromCommand($command);
        $value = self::extractValue($command, $type);

        return new self(
            text: $text,
            type: $type,
            value: $value,
            row: $row,
        );
    }

    /**
     * Extract value from command.
     *
     * @param string|null $command Command
     * @param ButtonType  $type    Button type
     *
     * @return string|null
     */
    private static function extractValue(?string $command, ButtonType $type): ?string
    {
        if ($command === null || $command === '') {
            return null;
        }

        if ($type === ButtonType::PHONE) {
            return null;
        }

        $parts = explode(':', $command, 2);

        return $parts[1] ?? null;
    }

    /**
     * Convert button to Telegram inline keyboard format.
     *
     * @return array<string, string>
     */
    public function toTelegramInline(): array
    {
        return match ($this->type) {
            ButtonType::CALLBACK => [
                'text' => $this->text,
                'callback_data' => $this->value ?? $this->text,
            ],
            ButtonType::URL => [
                'text' => $this->text,
                'url' => $this->value ?? '',
            ],
            default => [
                'text' => $this->text,
                'callback_data' => $this->text,
            ],
        };
    }

    /**
     * Convert button to Telegram reply keyboard format.
     *
     * @return array<string, mixed>
     */
    public function toTelegramReply(): array
    {
        return match ($this->type) {
            ButtonType::PHONE => [
                'text' => $this->text,
                'request_contact' => true,
            ],
            default => [
                'text' => $this->text,
            ],
        };
    }

    /**
     * Convert button to VK keyboard format.
     *
     * @return array<string, mixed>
     */
    public function toVkButton(): array
    {
        return match ($this->type) {
            ButtonType::CALLBACK => [
                'action' => [
                    'type' => 'callback',
                    'label' => $this->text,
                    'payload' => json_encode(['command' => $this->value ?? $this->text]),
                ],
            ],
            ButtonType::URL => [
                'action' => [
                    'type' => 'open_link',
                    'label' => $this->text,
                    'link' => $this->value ?? '',
                ],
            ],
            ButtonType::PHONE => [
                'action' => [
                    'type' => 'text',
                    'label' => $this->text,
                ],
            ],
            default => [
                'action' => [
                    'type' => 'text',
                    'label' => $this->text,
                ],
            ],
        };
    }
}
