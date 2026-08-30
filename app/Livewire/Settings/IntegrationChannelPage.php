<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Admin\Services\WebhookRegistrationService;
use App\Services\Settings\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Per-channel integration configuration page.
 *
 * Handles config forms for Telegram, Telegram AI bot, VK, and MAX channels.
 * Reads/writes via SettingsService (secrets are stored encrypted).
 *
 * The primary «Сохранить» button runs a verify-before-save flow via connect():
 *   1. Validate form fields (formErrors). On error → stop.
 *   2. Resolve the token for verification: the entered form value if non-empty,
 *      otherwise the currently stored token (so editing without re-entering the
 *      secret still works). If no token is available at all → set an error and stop.
 *   3. Call the matching WebhookRegistrationService::verifyX($token). On failure →
 *      set $webhookMessage / $webhookSuccess = false and return without saving.
 *   4. On success → persist via the existing saveX() path, register the webhook
 *      (for telegram|vk|max), and show the success notice.
 *
 * For telegram_ai the flow is identical except there is no webhook registration
 * step (webhook is set via `php artisan ai-bot:set-webhook`).
 *
 * save() (settings-only, no verify/webhook) remains available for tests that
 * call it directly.
 *
 * Note: telegram.group_id was moved to GeneralSettingsPage (the «Основные» screen).
 * The Telegram page now manages only the bot token and webhook secret key.
 *
 * Route: /admin/settings/integrations/{channel}
 *        where channel ∈ {telegram, telegram_ai, vk, max}
 *
 * Access: authenticated users via route middleware.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class IntegrationChannelPage extends Component
{
    /** @var string The current channel slug (telegram|telegram_ai|vk|max) */
    public string $channel = 'telegram';

    /** @var string|null */
    public ?string $telegram_token = null;

    /** @var string|null */
    public ?string $telegram_secret_key = null;

    /** @var string|null Optional fixed IP passed as `ip_address` to Telegram's setWebhook */
    public ?string $telegram_webhook_ip_address = null;

    /** @var string|null AI-bot token (pre-filled from settings, like the main bot) */
    public ?string $telegram_ai_token = null;

    /** @var string|null AI-bot webhook secret (pre-filled from settings, like the main bot) */
    public ?string $telegram_ai_secret = null;

    /** @var string|null */
    public ?string $vk_token = null;

    /** @var string|null */
    public ?string $vk_secret_key = null;

    /** @var string|null */
    public ?string $vk_confirm_code = null;

    /** @var string|null */
    public ?string $max_token = null;

    /** @var string|null */
    public ?string $max_secret_key = null;

    /** @var bool Config was persisted successfully in this request */
    public bool $saved = false;

    /** @var bool */
    public bool $channelConnected = false;

    /** @var array<string, string> */
    public array $formErrors = [];

    /** @var string|null Webhook registration result message */
    public ?string $webhookMessage = null;

    /** @var bool Webhook registration succeeded */
    public bool $webhookSuccess = false;

    /**
     * Mount the component with the channel slug from the route.
     */
    public function mount(string $channel, SettingsService $settings, ChannelStatusService $channelStatus): void
    {
        $this->channel = $channel;
        $this->loadFields($settings);

        $statuses = $channelStatus->all();
        $this->channelConnected = $statuses[$channel]['connected'] ?? false;
    }

    /**
     * Verify credentials, save config, then register the webhook — the «Сохранить» action.
     *
     * Step 1: run field validation (saveTelegram/Vk/Max validation rules).
     * Step 2: resolve the token to verify (entered value or stored fallback).
     * Step 3: call verifyX($token). On failure → set error notice, return without saving.
     * Step 4: persist via saveX(), register webhook (telegram|vk|max), show success notice.
     *
     * For telegram_ai: same flow (step 1-3 using verifyTelegram) but no webhook registration.
     */
    public function connect(SettingsService $settings, WebhookRegistrationService $webhook): void
    {
        $this->formErrors = [];
        $this->saved = false;
        $this->webhookMessage = null;
        $this->webhookSuccess = false;

        $validationError = $this->validateFields();
        if ($validationError !== null) {
            return;
        }

        [$tokenToVerify, $tokenError] = $this->resolveVerificationToken($settings);
        if ($tokenError !== null) {
            $this->webhookMessage = $tokenError;
            $this->webhookSuccess = false;

            return;
        }

        $verifyResult = match ($this->channel) {
            'telegram' => $webhook->verifyTelegram($tokenToVerify, null),
            'telegram_ai' => $webhook->verifyTelegram($tokenToVerify, null),
            'vk' => $webhook->verifyVk($tokenToVerify),
            'max' => $webhook->verifyMax($tokenToVerify),
            default => ['success' => false, 'message' => 'Неизвестный канал.'],
        };

        if (! $verifyResult['success']) {
            $this->webhookMessage = $verifyResult['message'];
            $this->webhookSuccess = false;

            return;
        }

        match ($this->channel) {
            'telegram' => $this->saveTelegram($settings),
            'telegram_ai' => $this->saveTelegramAi($settings),
            'vk' => $this->saveVk($settings),
            'max' => $this->saveMax($settings),
            default => $this->formErrors['channel'] = 'Неизвестный канал.',
        };

        if (! $this->saved) {
            return;
        }

        if ($this->channel === 'telegram_ai') {
            $botId = $verifyResult['botId'] ?? null;
            if ($botId !== null) {
                $settings->set('telegram_ai.id', $botId);
            }

            $botUsername = $verifyResult['botUsername'] ?? null;
            if ($botUsername !== null && $botUsername !== '') {
                $settings->set('telegram_ai.username', '@' . ltrim($botUsername, '@'));
            }

            $this->webhookSuccess = true;
            $this->webhookMessage = 'Настройки AI-бота сохранены.';

            return;
        }

        $result = match ($this->channel) {
            'telegram' => $webhook->registerTelegram(),
            'vk' => $webhook->registerVk(),
            'max' => $webhook->registerMax(),
            default => ['success' => false, 'message' => 'Неизвестный канал.'],
        };

        $this->webhookSuccess = $result['success'];
        $this->webhookMessage = $result['message'];
    }

    /**
     * Save the current channel's form values via SettingsService.
     *
     * Kept as a standalone method so unit tests can call it directly without
     * triggering the webhook step.
     */
    public function save(SettingsService $settings): void
    {
        $this->formErrors = [];
        $this->saved = false;

        match ($this->channel) {
            'telegram' => $this->saveTelegram($settings),
            'telegram_ai' => $this->saveTelegramAi($settings),
            'vk' => $this->saveVk($settings),
            'max' => $this->saveMax($settings),
            default => $this->formErrors['channel'] = 'Неизвестный канал.',
        };
    }

    /**
     * Register the webhook for the current channel (standalone action, kept
     * for backward-compatibility with existing tests).
     */
    public function registerWebhook(WebhookRegistrationService $webhook): void
    {
        $this->webhookMessage = null;
        $this->webhookSuccess = false;

        $result = match ($this->channel) {
            'telegram' => $webhook->registerTelegram(),
            'vk' => $webhook->registerVk(),
            'max' => $webhook->registerMax(),
            default => ['success' => false, 'message' => 'Неизвестный канал.'],
        };

        $this->webhookSuccess = $result['success'];
        $this->webhookMessage = $result['message'];
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.integration-channel-page');
    }

    /**
     * Run field-level validation for the current channel without persisting anything.
     *
     * Returns null when valid; sets $this->formErrors and returns the error key when
     * validation fails so connect() can abort before the verify step.
     *
     * @return string|null The first failing error key, or null on success.
     */
    private function validateFields(): ?string
    {
        if ($this->channel === 'telegram') {
            if (trim((string) $this->telegram_token) === '') {
                $this->formErrors['telegram_token'] = 'Укажите токен бота.';
            }
            if (trim((string) $this->telegram_secret_key) === '') {
                $this->formErrors['telegram_secret_key'] = 'Укажите секретный ключ Webhook.';
            }

            $webhookIp = trim((string) $this->telegram_webhook_ip_address);
            if ($webhookIp !== '' && filter_var($webhookIp, FILTER_VALIDATE_IP) === false) {
                $this->formErrors['telegram_webhook_ip_address'] = 'Некорректный IP-адрес.';
            }

            if (! empty($this->formErrors)) {
                return (string) array_key_first($this->formErrors);
            }
        }

        if ($this->channel === 'telegram_ai') {
            if (trim((string) $this->telegram_ai_token) === '') {
                $this->formErrors['telegram_ai_token'] = 'Укажите токен AI-бота.';
            }
            if (trim((string) $this->telegram_ai_secret) === '') {
                $this->formErrors['telegram_ai_secret'] = 'Укажите секретный ключ Webhook.';
            }

            if (! empty($this->formErrors)) {
                return (string) array_key_first($this->formErrors);
            }
        }

        if ($this->channel === 'vk') {
            if (trim((string) $this->vk_token) === '') {
                $this->formErrors['vk_token'] = 'Укажите токен VK.';
            }
            if (trim((string) $this->vk_secret_key) === '') {
                $this->formErrors['vk_secret_key'] = 'Укажите секретный ключ Webhook.';
            }
            if (trim((string) $this->vk_confirm_code) === '') {
                $this->formErrors['vk_confirm_code'] = 'Укажите код подтверждения.';
            }

            if (! empty($this->formErrors)) {
                return (string) array_key_first($this->formErrors);
            }
        }

        if ($this->channel === 'max') {
            if (trim((string) $this->max_token) === '') {
                $this->formErrors['max_token'] = 'Укажите токен MAX.';
            }
            if (trim((string) $this->max_secret_key) === '') {
                $this->formErrors['max_secret_key'] = 'Укажите секретный ключ Webhook.';
            }

            if (! empty($this->formErrors)) {
                return (string) array_key_first($this->formErrors);
            }
        }

        return null;
    }

    /**
     * Resolve the token to use for platform verification.
     *
     * Prefers the value the user typed in the form; falls back to the stored token when
     * the field is blank (edit-without-re-entering-secret use case).
     *
     * @param SettingsService $settings
     *
     * @return array{0: string, 1: string|null} [tokenString, errorMessage|null]
     */
    private function resolveVerificationToken(SettingsService $settings): array
    {
        $tokenField = match ($this->channel) {
            'telegram' => $this->telegram_token,
            'telegram_ai' => $this->telegram_ai_token,
            'vk' => $this->vk_token,
            'max' => $this->max_token,
            default => null,
        };

        $settingsKey = match ($this->channel) {
            'telegram' => 'telegram.token',
            'telegram_ai' => 'telegram_ai.token',
            'vk' => 'vk.token',
            'max' => 'max.token',
            default => null,
        };

        if ($tokenField !== null && $tokenField !== '') {
            return [$tokenField, null];
        }

        if ($settingsKey !== null) {
            $stored = (string) ($settings->get($settingsKey) ?? '');
            if ($stored !== '') {
                return [$stored, null];
            }
        }

        $label = match ($this->channel) {
            'telegram' => 'Telegram',
            'telegram_ai' => 'AI-бота',
            'vk' => 'VK',
            'max' => 'MAX',
            default => 'платформы',
        };

        return ['', 'Введите токен ' . $label . ' для проверки подключения.'];
    }

    /**
     * Populate all channel fields from SettingsService.
     */
    private function loadFields(SettingsService $settings): void
    {
        $this->telegram_token = (string) ($settings->get('telegram.token') ?? '');
        $this->telegram_secret_key = (string) ($settings->get('telegram.secret_key') ?? '');
        $this->telegram_webhook_ip_address = (string) ($settings->get('telegram.webhook_ip_address') ?? '');

        $this->telegram_ai_token = (string) ($settings->get('telegram_ai.token') ?? '');
        $this->telegram_ai_secret = (string) ($settings->get('telegram_ai.secret') ?? '');

        $this->vk_token = (string) ($settings->get('vk.token') ?? '');
        $this->vk_secret_key = (string) ($settings->get('vk.secret_key') ?? '');
        $this->vk_confirm_code = (string) ($settings->get('vk.confirm_code') ?? '');

        $this->max_token = (string) ($settings->get('max.token') ?? '');
        $this->max_secret_key = (string) ($settings->get('max.secret_key') ?? '');
    }

    /**
     * Validate and save Telegram main bot channel settings.
     *
     * telegram.group_id is no longer persisted here — it was moved to GeneralSettingsPage.
     * Unlike the token/secret fields, telegram.webhook_ip_address is not a secret and is
     * always persisted as entered — including blank, so clearing the field removes it.
     */
    private function saveTelegram(SettingsService $settings): void
    {
        if (! empty($this->formErrors)) {
            return;
        }

        if ($this->telegram_token !== '' && $this->telegram_token !== null) {
            $settings->set('telegram.token', $this->telegram_token);
        }
        if ($this->telegram_secret_key !== '' && $this->telegram_secret_key !== null) {
            $settings->set('telegram.secret_key', $this->telegram_secret_key);
        }

        $settings->set('telegram.webhook_ip_address', trim((string) $this->telegram_webhook_ip_address));

        $this->saved = true;
    }

    /**
     * Validate and save Telegram AI bot channel settings.
     */
    private function saveTelegramAi(SettingsService $settings): void
    {
        if (! empty($this->formErrors)) {
            return;
        }

        if ($this->telegram_ai_token !== '' && $this->telegram_ai_token !== null) {
            $settings->set('telegram_ai.token', $this->telegram_ai_token);
        }
        if ($this->telegram_ai_secret !== '' && $this->telegram_ai_secret !== null) {
            $settings->set('telegram_ai.secret', $this->telegram_ai_secret);
        }

        $this->saved = true;
    }

    /**
     * Validate and save VK channel settings.
     */
    private function saveVk(SettingsService $settings): void
    {
        if (! empty($this->formErrors)) {
            return;
        }

        if ($this->vk_token !== '') {
            $settings->set('vk.token', $this->vk_token);
        }
        if ($this->vk_secret_key !== '') {
            $settings->set('vk.secret_key', $this->vk_secret_key);
        }
        if ($this->vk_confirm_code !== '') {
            $settings->set('vk.confirm_code', $this->vk_confirm_code);
        }

        $this->saved = true;
    }

    /**
     * Validate and save MAX channel settings.
     */
    private function saveMax(SettingsService $settings): void
    {
        if (! empty($this->formErrors)) {
            return;
        }

        if ($this->max_token !== '') {
            $settings->set('max.token', $this->max_token);
        }
        if ($this->max_secret_key !== '') {
            $settings->set('max.secret_key', $this->max_secret_key);
        }

        $this->saved = true;
    }
}
