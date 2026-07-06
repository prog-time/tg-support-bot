<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\AvitoVerificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Avito integration screen — Avito Messenger API credentials.
 *
 * Manages the paid module's API access fields (client_id, client_secret,
 * base_url, webhook_secret), stored via SettingsService under the `avito.*`
 * registry keys. The module reads these from config('avito.*');
 * {@see \App\Providers\AvitoSettingsBridgeServiceProvider} pushes the stored DB
 * values into that config tree at boot.
 *
 * The primary «Сохранить» button runs a verify-before-save flow via connect():
 *   1. Validate that Client ID and Client Secret are available (entered or stored).
 *   2. Resolve the secret (entered value or the stored fallback).
 *   3. Call {@see AvitoVerificationService::verify()} — OAuth token + accounts/self.
 *      On failure → show the error and persist nothing.
 *   4. On success → persist the credentials AND auto-capture the account id
 *      (`user_id`) returned by accounts/self — it is never entered manually,
 *      mirroring how the Telegram AI bot id/@username are captured from getMe.
 *
 * The subscription «Лицензионный ключ» is NOT managed here — it lives on the
 * «Основные» general settings screen ({@see GeneralSettingsPage}).
 *
 * Secret fields are pre-filled from settings and rendered as password inputs; a
 * blank submission keeps the existing stored secret.
 *
 * Admin-only: non-admins are redirected to the general settings screen.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class AvitoIntegrationPage extends Component
{
    /** Default Avito API base URL when none is stored. */
    private const DEFAULT_BASE_URL = 'https://api.avito.ru';

    /** @var string Avito OAuth client id (non-secret). */
    public string $client_id = '';

    /** @var string Avito OAuth client secret (secret). */
    public string $client_secret = '';

    /** @var string Avito API base URL (non-secret, defaults to api.avito.ru). */
    public string $base_url = '';

    /** @var string Secret embedded in the webhook URL path (secret). */
    public string $webhook_secret = '';

    /** @var string Resolved Avito account id (captured from accounts/self; read-only). */
    public string $user_id = '';

    /** @var bool Whether a client secret is already stored. */
    public bool $hasClientSecret = false;

    /** @var bool Whether a webhook secret is already stored. */
    public bool $hasWebhookSecret = false;

    /** @var array<string, string> */
    public array $formErrors = [];

    /** @var string|null Verify-before-save result message. */
    public ?string $verifyMessage = null;

    /** @var bool Whether the last verify+save succeeded. */
    public bool $verifySuccess = false;

    /**
     * Redirect non-admins; load current values.
     */
    public function mount(SettingsService $settings): void
    {
        if (! Auth::user()?->isAdmin()) {
            $this->redirectRoute('admin.settings.general', navigate: true);

            return;
        }

        $this->client_id = (string) ($settings->get('avito.client_id') ?? '');
        $this->client_secret = (string) ($settings->get('avito.client_secret') ?? '');
        $this->base_url = (string) ($settings->get('avito.base_url') ?? '');
        $this->webhook_secret = (string) ($settings->get('avito.webhook_secret') ?? '');
        $this->user_id = (string) ($settings->get('avito.user_id') ?? '');

        $this->hasClientSecret = $settings->has('avito.client_secret');
        $this->hasWebhookSecret = $settings->has('avito.webhook_secret');
    }

    /**
     * Verify the Avito API credentials, then persist them — the «Сохранить» action.
     *
     * Runs accounts/self verification against the entered (or stored) credentials
     * and auto-captures the account id as `avito.user_id`. Nothing is persisted on
     * a verification failure.
     */
    public function connect(SettingsService $settings, AvitoVerificationService $verifier): void
    {
        if (! Auth::user()?->isAdmin()) {
            return;
        }

        $this->formErrors = [];
        $this->verifyMessage = null;
        $this->verifySuccess = false;

        // Step 1: field validation.
        if ($this->validateFields() !== null) {
            return;
        }

        // Step 2: resolve the secret — entered value, else the stored one.
        $clientId = trim($this->client_id);
        $clientSecret = trim($this->client_secret);

        if ($clientSecret === '') {
            $clientSecret = (string) ($settings->get('avito.client_secret') ?? '');
        }

        // Step 3: verify against the Avito API.
        $result = $verifier->verify($clientId, $clientSecret, trim($this->base_url));

        if (! $result['success']) {
            $this->verifyMessage = $result['message'];
            $this->verifySuccess = false;

            return;
        }

        // Step 4: persist credentials + the auto-captured account id.
        $settings->set('avito.client_id', $clientId);
        $settings->set('avito.base_url', trim($this->base_url) ?: self::DEFAULT_BASE_URL);

        // Secret: write only when a value was entered (blank keeps the stored one).
        if (trim($this->client_secret) !== '') {
            $settings->set('avito.client_secret', trim($this->client_secret));
        }

        // webhook_secret is optional: persisted as-is (blank clears it → open endpoint).
        $settings->set('avito.webhook_secret', trim($this->webhook_secret));

        // Auto-captured account id — the messenger user_id (never entered manually).
        $accountId = (string) $result['accountId'];
        $settings->set('avito.user_id', $accountId);
        $this->user_id = $accountId;

        $this->hasClientSecret = $settings->has('avito.client_secret');
        $this->hasWebhookSecret = $settings->has('avito.webhook_secret');

        $this->verifySuccess = true;
        $this->verifyMessage = $result['accountName'] !== null
            ? 'Подключено. Аккаунт Avito: ' . $result['accountName'] . ' (ID ' . $accountId . ').'
            : 'Подключено. ID аккаунта Avito: ' . $accountId . '.';
    }

    /**
     * Field-level validation. Returns the first failing key, or null when valid.
     *
     * @return string|null
     */
    private function validateFields(): ?string
    {
        if (trim($this->client_id) === '') {
            $this->formErrors['client_id'] = 'Укажите Client ID.';
        }

        // The secret may be left blank when one is already stored (edit without
        // re-entering it); only require it when nothing is on file yet.
        if (trim($this->client_secret) === '' && ! $this->hasClientSecret) {
            $this->formErrors['client_secret'] = 'Укажите Client Secret.';
        }

        return empty($this->formErrors) ? null : (string) array_key_first($this->formErrors);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.avito-integration-page');
    }
}
