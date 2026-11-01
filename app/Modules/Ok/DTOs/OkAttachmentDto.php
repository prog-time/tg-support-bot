<?php

namespace App\Modules\Ok\DTOs;

/**
 * One incoming OK attachment, reduced to what the forward needs: a downloadable
 * file (`url` + Telegram method) or a text description (contact, location, ...).
 */
readonly class OkAttachmentDto
{
    private const FILE_TYPES = [
        'IMAGE' => ['sendPhoto', 'photo', '🖼 Изображение'],
        'STICKER' => ['sendPhoto', 'sticker', '🏷 Стикер'],
        'VIDEO' => ['sendVideo', 'video_note', '🎬 Видео'],
        'AUDIO' => ['sendAudio', 'audio_message', '🎵 Аудио'],
        'FILE' => ['sendDocument', 'document', '📎 Файл'],
    ];

    /**
     * @param string      $method   Telegram method
     * @param string|null $text     Message text; for a file it is the fallback link
     * @param string|null $url      File to download; null for a text description
     * @param string|null $fileType message_attachments.file_type
     */
    public function __construct(
        public string $method,
        public ?string $text,
        public ?string $url = null,
        public ?string $fileType = null,
    ) {
    }

    /**
     * @param array $attachment OK attachment: {type, payload}
     *
     * @return self
     */
    public static function fromArray(array $attachment): self
    {
        $type = (string) ($attachment['type'] ?? '');
        $payload = is_array($attachment['payload'] ?? null) ? $attachment['payload'] : [];
        $url = isset($payload['url']) ? (string) $payload['url'] : '';

        if (isset(self::FILE_TYPES[$type]) && $url !== '') {
            [$method, $fileType, $label] = self::FILE_TYPES[$type];

            return new self($method, "{$label}: {$url}", $url, $fileType);
        }

        return new self('sendMessage', self::describe($type, $payload, $url));
    }

    /**
     * @param string $type
     * @param array  $payload
     * @param string $url
     *
     * @return string
     */
    private static function describe(string $type, array $payload, string $url): string
    {
        return match ($type) {
            'CONTACT' => self::describeContact($payload),
            'LOCATION' => self::describeLocation($payload),
            'SHARE' => self::describeLink('🔗 Ссылка', '🔗 Пользователь поделился материалом из OK', $payload, $url),
            'MUSIC' => self::describeLink('🎵 Музыка', '🎵 Пользователь отправил музыку из OK', $payload, $url),
            'CALL' => '📞 Звонок (' . ($payload['type'] ?? 'AUDIO') . '), длительность: ' . ($payload['duration'] ?? 0),
            'PRESENT' => '🎁 Пользователь отправил подарок',
            default => self::describeLink("📎 Вложение {$type}", "📎 Вложение {$type} без ссылки", $payload, $url),
        };
    }

    /**
     * OK may send a reshare with only its id. No URL can be built from the id, so
     * the manager is told to look in the OK chat instead of getting an empty link.
     *
     * @param string $withUrl    Label before the URL
     * @param string $withoutUrl Text when there is no URL
     * @param array  $payload
     * @param string $url
     *
     * @return string
     */
    private static function describeLink(string $withUrl, string $withoutUrl, array $payload, string $url): string
    {
        if ($url !== '') {
            return "{$withUrl}: {$url}";
        }

        $id = isset($payload['id']) ? " (ID {$payload['id']})" : '';

        return "{$withoutUrl}{$id}. Посмотреть можно только в чате OK.";
    }

    /**
     * @param array $payload
     *
     * @return string
     */
    private static function describeContact(array $payload): string
    {
        return "📞 Контакт\nИмя: " . ($payload['name'] ?? '') . "\nТелефон: " . ($payload['phone'] ?? '');
    }

    /**
     * @param array $payload
     *
     * @return string
     */
    private static function describeLocation(array $payload): string
    {
        $lat = $payload['latitude'] ?? '';
        $lon = $payload['longitude'] ?? '';

        return "📍 Геопозиция\nШирота: {$lat}\nДолгота: {$lon}\nhttps://maps.google.com/?q={$lat},{$lon}";
    }
}
