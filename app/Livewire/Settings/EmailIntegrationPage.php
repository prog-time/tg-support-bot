<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\EmailVerificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Email integration screen — generic IMAP/SMTP credentials for the built-in
 * Email module. Works with any provider (Yandex, Mail.ru, Gmail with an
 * app-password, a corporate mailbox) — there are no per-provider presets in
 * this iteration (see issue #214 "Не делаем в этой задаче").
 *
 * Mirrors {@see AvitoIntegrationPage}: fields are stored via SettingsService
 * under the `email.*` registry keys, and the primary «Сохранить» button runs
 * a verify-before-save flow via connect():
 *   1. Validate the required fields (host/username/password for both sides).
 *   2. Resolve the password (entered value or the stored fallback).
 *   3. Call {@see EmailVerificationService::verify()} — a real IMAP login AND
 *      a real SMTP handshake. On failure → show the error and persist nothing.
 *   4. On success → persist every field via SettingsService.
 *
 * The password field is pre-filled as "already stored" and rendered as a
 * password input; a blank submission keeps the existing stored password.
 *
 * Admin-only: non-admins are redirected to the general settings screen.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class EmailIntegrationPage extends Component
{
    public string $imap_host = '';

    public string $imap_port = '993';

    public string $imap_encryption = 'ssl';

    public string $smtp_host = '';

    public string $smtp_port = '587';

    public string $smtp_encryption = 'tls';

    public string $username = '';

    public string $password = '';

    public string $from_address = '';

    public string $from_name = '';

    /** @var bool Whether a password is already stored. */
    public bool $hasPassword = false;

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

        $this->imap_host = (string) ($settings->get('email.imap_host') ?? '');
        $this->imap_port = (string) ($settings->get('email.imap_port') ?: '993');
        $this->imap_encryption = (string) ($settings->get('email.imap_encryption') ?: 'ssl');
        $this->smtp_host = (string) ($settings->get('email.smtp_host') ?? '');
        $this->smtp_port = (string) ($settings->get('email.smtp_port') ?: '587');
        $this->smtp_encryption = (string) ($settings->get('email.smtp_encryption') ?: 'tls');
        $this->username = (string) ($settings->get('email.username') ?? '');
        $this->from_address = (string) ($settings->get('email.from_address') ?? '');
        $this->from_name = (string) ($settings->get('email.from_name') ?? '');

        $this->hasPassword = $settings->has('email.password');
    }

    /**
     * Verify the IMAP/SMTP credentials, then persist them — the «Сохранить» action.
     */
    public function connect(SettingsService $settings, EmailVerificationService $verifier): void
    {
        if (! Auth::user()?->isAdmin()) {
            return;
        }

        $this->formErrors = [];
        $this->verifyMessage = null;
        $this->verifySuccess = false;

        if ($this->validateFields() !== null) {
            return;
        }

        $password = trim($this->password);
        if ($password === '') {
            $password = (string) ($settings->get('email.password') ?? '');
        }

        $result = $verifier->verify(
            imap: [
                'host' => trim($this->imap_host),
                'port' => (int) $this->imap_port,
                'encryption' => trim($this->imap_encryption),
                'username' => trim($this->username),
                'password' => $password,
            ],
            smtp: [
                'host' => trim($this->smtp_host),
                'port' => (int) $this->smtp_port,
                'encryption' => trim($this->smtp_encryption),
                'username' => trim($this->username),
                'password' => $password,
            ],
        );

        if (! $result['success']) {
            $this->verifyMessage = $result['message'];
            $this->verifySuccess = false;

            return;
        }

        $settings->set('email.imap_host', trim($this->imap_host));
        $settings->set('email.imap_port', (int) $this->imap_port);
        $settings->set('email.imap_encryption', trim($this->imap_encryption));
        $settings->set('email.smtp_host', trim($this->smtp_host));
        $settings->set('email.smtp_port', (int) $this->smtp_port);
        $settings->set('email.smtp_encryption', trim($this->smtp_encryption));
        $settings->set('email.username', trim($this->username));
        $settings->set('email.from_address', trim($this->from_address));
        $settings->set('email.from_name', trim($this->from_name));

        // Password: write only when a value was entered (blank keeps the stored one).
        if (trim($this->password) !== '') {
            $settings->set('email.password', trim($this->password));
        }

        $this->hasPassword = $settings->has('email.password');

        $this->verifySuccess = true;
        $this->verifyMessage = $result['message'];
    }

    /**
     * Field-level validation. Returns the first failing key, or null when valid.
     *
     * @return string|null
     */
    private function validateFields(): ?string
    {
        if (trim($this->imap_host) === '') {
            $this->formErrors['imap_host'] = 'Укажите IMAP-хост.';
        }

        if (trim($this->smtp_host) === '') {
            $this->formErrors['smtp_host'] = 'Укажите SMTP-хост.';
        }

        if (trim($this->username) === '') {
            $this->formErrors['username'] = 'Укажите логин почтового ящика.';
        }

        // The password may be left blank when one is already stored (edit
        // without re-entering it); only require it when nothing is on file yet.
        if (trim($this->password) === '' && ! $this->hasPassword) {
            $this->formErrors['password'] = 'Укажите пароль.';
        }

        if (trim($this->from_address) === '') {
            $this->formErrors['from_address'] = 'Укажите адрес отправителя.';
        }

        return empty($this->formErrors) ? null : (string) array_key_first($this->formErrors);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.email-integration-page');
    }
}
