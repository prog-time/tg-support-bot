<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Services\Settings\SettingsService;

/**
 * Computes the connection status for each supported platform channel.
 *
 * A channel is considered "connected" when all required non-secret keys
 * are present AND at least one secret credential key is set.
 *
 * Required keys per platform:
 *   Telegram    — telegram.token (secret), telegram.secret_key (secret)
 *   Telegram AI — telegram_ai.token (secret)
 *   VK          — vk.token (secret), vk.secret_key (secret), vk.confirm_code (secret)
 *   MAX         — max.token (secret), max.secret_key (secret)
 *
 * Note: telegram.group_id moved to the «Основные» general settings screen —
 *       it is no longer part of the Telegram channel connection check.
 */
class ChannelStatusService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * Return connection status for all channels.
     *
     * @return array<string, array{connected: bool, label: string}>
     */
    public function all(): array
    {
        return [
            'telegram' => $this->telegram(),
            'telegram_ai' => $this->telegramAi(),
            'vk' => $this->vk(),
            'max' => $this->max(),
            'email' => $this->email(),
        ];
    }

    /**
     * Telegram channel status.
     *
     * Connected when the bot token and webhook secret key are both set.
     * The group ID is configured on the «Основные» general settings screen
     * and is no longer part of this check.
     *
     * @return array{connected: bool, label: string}
     */
    public function telegram(): array
    {
        $connected = $this->isNonEmpty('telegram.token')
            && $this->isNonEmpty('telegram.secret_key');

        return [
            'connected' => $connected,
            'label' => $connected ? 'Подключён' : 'Не настроен',
        ];
    }

    /**
     * Telegram AI bot channel status.
     *
     * @return array{connected: bool, label: string}
     */
    public function telegramAi(): array
    {
        $connected = $this->isNonEmpty('telegram_ai.token');

        return [
            'connected' => $connected,
            'label' => $connected ? 'Подключён' : 'Не настроен',
        ];
    }

    /**
     * VK channel status.
     *
     * @return array{connected: bool, label: string}
     */
    public function vk(): array
    {
        $connected = $this->isNonEmpty('vk.token')
            && $this->isNonEmpty('vk.secret_key')
            && $this->isNonEmpty('vk.confirm_code');

        return [
            'connected' => $connected,
            'label' => $connected ? 'Подключён' : 'Не настроен',
        ];
    }

    /**
     * MAX channel status.
     *
     * @return array{connected: bool, label: string}
     */
    public function max(): array
    {
        $connected = $this->isNonEmpty('max.token')
            && $this->isNonEmpty('max.secret_key');

        return [
            'connected' => $connected,
            'label' => $connected ? 'Подключён' : 'Не настроен',
        ];
    }

    /**
     * Email channel status.
     *
     * Connected when the IMAP host, SMTP host, username and password are all set.
     *
     * @return array{connected: bool, label: string}
     */
    public function email(): array
    {
        $connected = $this->isNonEmpty('email.imap_host')
            && $this->isNonEmpty('email.smtp_host')
            && $this->isNonEmpty('email.username')
            && $this->isNonEmpty('email.password');

        return [
            'connected' => $connected,
            'label' => $connected ? 'Подключён' : 'Не настроен',
        ];
    }

    /**
     * Whether a settings key resolves to a non-empty value.
     */
    private function isNonEmpty(string $key): bool
    {
        $value = $this->settings->get($key);

        return $value !== null && $value !== '' && $value !== 0;
    }
}
