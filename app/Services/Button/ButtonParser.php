<?php

declare(strict_types=1);

namespace App\Services\Button;

use App\DTOs\Button\ButtonDto;
use App\DTOs\Button\ParsedMessageDto;

/**
 * Button syntax parser from message text.
 *
 * Syntax: [[Button text|type:value]]
 *
 * Examples:
 * - [[Open site|url:https://example.com]] - URL button
 * - [[Go back|callback:back]] - inline callback button
 * - [[Share phone|phone]] - phone request button
 * - [[Response text]] - reply keyboard with text
 */
class ButtonParser
{
    /**
     * Regular expression for finding buttons.
     * Format: [[text]] or [[text|command]]
     */
    private const BUTTON_PATTERN = "/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/";

    /**
     * Parse message text and extract buttons.
     *
     * @param string $message Original message text
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

        $cleanText = $this->removeButtonSyntax($message);

        return new ParsedMessageDto(
            text: $cleanText,
            buttons: $buttons
        );
    }

    /**
     * Remove button syntax from text.
     *
     * @param string $message Original text
     *
     * @return string
     */
    private function removeButtonSyntax(string $message): string
    {
        $cleanText = preg_replace(self::BUTTON_PATTERN, '', $message);
        $cleanText = rtrim($cleanText ?? '');
        $cleanText = preg_replace('/\n{3,}/', "\n\n", $cleanText);

        return $cleanText ?? '';
    }

    /**
     * Check if text contains button syntax.
     *
     * @param string $message Text to check
     *
     * @return bool
     */
    public function hasButtons(string $message): bool
    {
        return preg_match(self::BUTTON_PATTERN, $message) === 1;
    }
}
