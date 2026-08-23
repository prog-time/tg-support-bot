<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\PushSubscription;
use App\Modules\Admin\Services\WebhookRegistrationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Custom Livewire full-page component for the «Основные» settings screen.
 *
 * Manages:
 *   - telegram.template_topic_name — Telegram forum topic name template
 *   - telegram.group_id            — Telegram supergroup ID for receiving messages
 *
 * Reads and writes via SettingsService (DB → config() fallback, cache-backed).
 * Access: admins see and edit the «Обращения» config card; managers may open
 * this screen too but only see the «Оповещения о новых сообщениях» card —
 * the config form is hidden in the view and save() refuses non-admins.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class GeneralSettingsPage extends Component
{
    /** @var string|null Telegram forum topic name template */
    public ?string $template_topic_name = null;

    /** @var string|null Telegram supergroup ID for receiving messages (e.g. -100XXXXXXXXXX) */
    public ?string $group_id = null;

    /** @var bool Show success banner */
    public bool $saved = false;

    /** @var array<string, string> */
    public array $formErrors = [];

    /** @var string VAPID public key for Web Push subscription (empty when not yet generated) */
    public string $vapidPublicKey = '';

    /**
     * Load current values from SettingsService on mount.
     */
    public function mount(SettingsService $settings): void
    {
        $this->template_topic_name = (string) ($settings->get('telegram.template_topic_name') ?? '');
        $this->group_id = (string) ($settings->get('telegram.group_id') ?? '');
        $this->vapidPublicKey = (string) ($settings->get('webpush.vapid_public_key') ?? '');
    }

    /**
     * Save the form values via SettingsService.
     */
    public function save(SettingsService $settings): void
    {
        /**
         * The «Обращения» config form is admin-only; managers only see the
         * notifications card. Refuse a crafted save from a non-admin.
         */
        if (! Auth::user()?->isAdmin()) {
            return;
        }

        $this->formErrors = [];
        $this->saved = false;

        /**
         * Normalize: a pasted group ID often carries leading/trailing whitespace,
         * which would make getChat fail even for a correct ID.
         */
        $this->group_id = trim((string) ($this->group_id ?? ''));

        if (strlen((string) $this->template_topic_name) > 255) {
            $this->formErrors['template_topic_name'] = 'Максимальная длина — 255 символов.';
        }

        /**
         * Optional: the Telegram supergroup is an addition. Empty group_id means
         * admin-panel-only (no group mirroring). Validate length only when filled.
         */
        if (strlen((string) $this->group_id) > 50) {
            $this->formErrors['group_id'] = 'Максимальная длина — 50 символов.';
        }

        if (! empty($this->formErrors)) {
            return;
        }

        /**
         * Verify-before-save for the group. When a group ID is provided, check
         * (1) the Telegram integration works (valid bot token), (2) the bot is
         * a member of that group, and (3) the bot has administrator rights
         * there. Nothing is persisted on failure.
         */
        if (trim((string) $this->group_id) !== '') {
            $token = (string) ($settings->get('telegram.token') ?? '');

            if ($token === '') {
                $this->formErrors['group_id'] = 'Сначала настройте токен Telegram-бота в «Интеграции → Telegram».';

                return;
            }

            $verify = app(WebhookRegistrationService::class)->verifyTelegram($token, $this->group_id);

            if (! $verify['success']) {
                $this->formErrors['group_id'] = $verify['message'];

                return;
            }
        }

        $settings->set('telegram.template_topic_name', $this->template_topic_name ?? '');
        $settings->set('telegram.group_id', $this->group_id ?? '');

        $this->saved = true;
    }

    /**
     * Persist a browser's Web Push subscription (from `PushManager.subscribe()`,
     * called client-side after `Notification.requestPermission()` resolves to
     * "granted"). Upserted by `endpoint` so re-subscribing the same browser
     * updates its keys instead of creating a duplicate row.
     *
     * @param array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}} $subscription
     *
     * @return void
     */
    public function savePushSubscription(array $subscription): void
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        $publicKey = (string) ($subscription['keys']['p256dh'] ?? '');
        $authToken = (string) ($subscription['keys']['auth'] ?? '');

        if ($endpoint === '' || $publicKey === '' || $authToken === '' || Auth::id() === null) {
            return;
        }

        PushSubscription::updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => Auth::id(),
                'public_key' => $publicKey,
                'auth_token' => $authToken,
            ]
        );
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.general-settings-page');
    }
}
