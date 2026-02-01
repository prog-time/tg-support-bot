<?php

namespace App\Actions\Telegram;

class ConversionMessageText {

    /**
     * Convert message to MarkdownV2 format.
     *
     * @param string $text
     * @param array $entities
     * @return string
     */
    public static function conversionMarkdownFormat(string $text, array $entities): string
    {
        usort($entities, fn($a, $b) => $b['offset'] <=> $a['offset']);

        foreach ($entities as $entity) {
            $offset = $entity['offset'];
            $length = $entity['length'];
            $type = $entity['type'];
            $part = mb_substr($text, $offset, $length);

            switch ($type) {
                case 'bold':
                    $wrapped = "**{$part}**";
                    break;
                case 'italic':
                    $wrapped = "_{$part}_";
                    break;
                case 'code':
                    $wrapped = "`{$part}`";
                    break;
                case 'pre':
                    $wrapped = "```\n{$part}\n```";
                    break;
                case 'text_link':
                    $wrapped = "[{$part}]({$entity['url']})";
                    break;
                default:
                    $wrapped = $part;
            }

            $text = mb_substr($text, 0, $offset) . $wrapped . mb_substr($text, $offset + $length);
        }

        return $text;
    }

}
