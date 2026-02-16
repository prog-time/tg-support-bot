<?php

namespace App\Actions\Telegram;

class ConversionMessageText
{
    /**
     * Entity types that require MarkdownV2 conversion.
     */
    private const FORMATTING_ENTITY_TYPES = [
        'bold',
        'italic',
        'underline',
        'strikethrough',
        'spoiler',
        'code',
        'pre',
        'text_link',
        'blockquote',
        'custom_emoji',
    ];

    /**
     * Check if entities contain formatting types that require MarkdownV2.
     *
     * @param array $entities
     * @return bool
     */
    public static function hasFormattingEntities(array $entities): bool
    {
        foreach ($entities as $entity) {
            if (in_array($entity['type'], self::FORMATTING_ENTITY_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert message to MarkdownV2 format.
     * Supports nested and overlapping entities via event-based processing.
     *
     * @param string $text
     * @param array $entities
     * @return string
     */
    public static function conversionMarkdownFormat(string $text, array $entities): string
    {
        $entities = array_values(array_filter(
            $entities,
            fn ($e) => in_array($e['type'], self::FORMATTING_ENTITY_TYPES, true)
        ));

        if (empty($entities)) {
            return self::escapeMarkdownV2($text);
        }

        $events = [];
        foreach ($entities as $idx => $entity) {
            $events[] = [$entity['offset'], 'open', $idx];
            $events[] = [$entity['offset'] + $entity['length'], 'close', $idx];
        }

        usort($events, function ($a, $b) use ($entities) {
            if ($a[0] !== $b[0]) {
                return $a[0] - $b[0];
            }
            // At same position: closes before opens
            if ($a[1] !== $b[1]) {
                return $a[1] === 'close' ? -1 : 1;
            }
            // Same type at same position
            if ($a[1] === 'open') {
                // Longer (outer) entities open first
                return $entities[$b[2]]['length'] - $entities[$a[2]]['length'];
            }
            // Shorter (inner) entities close first
            return $entities[$a[2]]['length'] - $entities[$b[2]]['length'];
        });

        $result = '';
        $lastPos = 0;
        $openEntities = [];

        foreach ($events as [$pos, $type, $entityIdx]) {
            $entity = $entities[$entityIdx];

            if ($pos > $lastPos) {
                $segment = mb_substr($text, $lastPos, $pos - $lastPos);
                $insideCode = !empty(array_filter($openEntities, fn ($e) => in_array($e['type'], ['code', 'pre'])));
                $result .= $insideCode ? self::escapeCode($segment) : self::escapeMarkdownV2($segment);
                $lastPos = $pos;
            }

            if ($type === 'open') {
                $result .= self::getOpenMarker($entity);
                $openEntities[$entityIdx] = $entity;
            } else {
                $result .= self::getCloseMarker($entity);
                unset($openEntities[$entityIdx]);
            }
        }

        if ($lastPos < mb_strlen($text)) {
            $result .= self::escapeMarkdownV2(mb_substr($text, $lastPos));
        }

        return $result;
    }

    /**
     * Get the opening MarkdownV2 marker for an entity.
     *
     * @param array $entity
     * @return string
     */
    private static function getOpenMarker(array $entity): string
    {
        return match ($entity['type']) {
            'bold' => '*',
            'italic' => '_',
            'underline' => '__',
            'strikethrough' => '~',
            'spoiler' => '||',
            'code' => '`',
            'pre' => '```' . ($entity['language'] ?? '') . "\n",
            'text_link' => '[',
            'blockquote' => '>',
            default => '',
        };
    }

    /**
     * Get the closing MarkdownV2 marker for an entity.
     *
     * @param array $entity
     * @return string
     */
    private static function getCloseMarker(array $entity): string
    {
        return match ($entity['type']) {
            'bold' => '*',
            'italic' => '_',
            'underline' => '__',
            'strikethrough' => '~',
            'spoiler' => '||',
            'code' => '`',
            'pre' => "\n```",
            'text_link' => '](' . self::escapeUrl($entity['url'] ?? '') . ')',
            default => '',
        };
    }

    /**
     * Escape special characters for MarkdownV2.
     *
     * @param string $text
     * @return string
     */
    private static function escapeMarkdownV2(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text) ?? $text;
    }

    /**
     * Escape characters inside code/pre blocks.
     *
     * @param string $text
     * @return string
     */
    private static function escapeCode(string $text): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $text);
    }

    /**
     * Escape characters inside URL part of text_link.
     *
     * @param string $url
     * @return string
     */
    private static function escapeUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }
}
