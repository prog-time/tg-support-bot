<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\OkVerificationService;
use App\Modules\Admin\Services\WebhookRegistrationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Odnoklassniki integration screen, admin-only.
 *
 * «Сохранить» checks the token against the OK API and saves nothing on failure,
 * then subscribes the webhook, so a changed token or secret needs no other step.
 * Stored secrets are never sent to the browser: a blank field keeps the stored value.
 */
#[Layout('layouts.admin-settings')]
class OkIntegrationPage extends Component
{
    /** @var string Group token (secret). */
    public string $token = '';

    /** @var string Secret embedded in the webhook URL path. */
    public string $webhook_secret = '';

    /** @var bool */
    public bool $hasToken = false;

    /** @var bool */
    public bool $hasWebhookSecret = false;

    /** @var array<string, string> */
    public array $formErrors = [];

    /** @var string|null */
    public ?string $verifyMessage = null;

    /** @var bool */
    public bool $verifySuccess = false;

    /**
     * Redirect non-admins and load the state of the stored secrets.
     *
     * @param SettingsService $settings
     *
     * @return void
     */
    public function mount(SettingsService $settings): void
    {
        if (! Auth::user()?->isAdmin()) {
            $this->redirectRoute('admin.settings.general', navigate: true);

            return;
        }

        $this->hasToken = $settings->has('ok.token');
        $this->hasWebhookSecret = $settings->has('ok.webhook_secret');
    }

    /**
     * Verify the token, persist the settings and subscribe the webhook — the
     * «Сохранить» action.
     *
     * @param SettingsService            $settings
     * @param OkVerificationService      $verifier
     * @param WebhookRegistrationService $webhook
     *
     * @return void
     */
    public function connect(SettingsService $settings, OkVerificationService $verifier, WebhookRegistrationService $webhook): void
    {
        if (! Auth::user()?->isAdmin()) {
            return;
        }

        $this->formErrors = [];
        $this->verifyMessage = null;
        $this->verifySuccess = false;

        $token = trim($this->token);
        $secret = trim($this->webhook_secret);

        if ($token === '' && ! $this->hasToken) {
            $this->formErrors['token'] = 'Укажите токен группы.';
        }

        if ($secret === '' && ! $this->hasWebhookSecret) {
            $this->formErrors['webhook_secret'] = 'Укажите секретный ключ вебхука.';
        }

        if (! empty($this->formErrors)) {
            return;
        }

        $result = $verifier->verify($token !== '' ? $token : (string) $settings->get('ok.token'));
        $this->verifyMessage = $result['message'];

        if (! $result['success']) {
            return;
        }

        if ($token !== '') {
            $settings->set('ok.token', $token);
        }

        if ($secret !== '') {
            $settings->set('ok.webhook_secret', $secret);
        }

        $this->token = '';
        $this->webhook_secret = '';
        $this->hasToken = true;
        $this->hasWebhookSecret = true;

        $registration = $webhook->registerOk();
        $this->verifySuccess = $registration['success'];
        $this->verifyMessage = $registration['success']
            ? 'Настройки сохранены, вебхук подписан.'
            : 'Настройки сохранены, но вебхук не подписан. Нажмите «Сохранить» ещё раз. ' . $registration['message'];
    }

    /**
     * @return View
     */
    public function render(): View
    {
        return view('livewire.settings.ok-integration-page');
    }
}
