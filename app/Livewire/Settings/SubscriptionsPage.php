<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Services\Licensing\LicenseClient;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «Подписки» screen — a single license key covering all paid modules.
 *
 * The admin pastes the license key and clicks «Проверить»: the key is checked
 * against the License Server ({@see LicenseClient}), and the modules it grants
 * are listed in a table (name, expiry date, status). On a successful check the
 * key is persisted under `license.key` and mirrored into each installed
 * module's own license setting key (config('license.module_keys')) so every
 * module's LicenseGuard picks it up; their cached verdicts are dropped so the
 * new key takes effect immediately.
 *
 * Admin-only: non-admins are redirected to the general settings screen.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class SubscriptionsPage extends Component
{
    /** @var string New license key entered by the admin (blank = use stored key). */
    public string $license_key = '';

    /** @var bool Whether a license key is already stored. */
    public bool $hasKey = false;

    /** @var bool Whether a check has been run in this request (table is shown). */
    public bool $checked = false;

    /**
     * @var array<int, array{product: string, name: string, valid_until: string|null, status: string}>
     *                                                                                                 Modules granted by the key, populated after «Проверить».
     */
    public array $products = [];

    /** @var string|null Error message from the last check. */
    public ?string $errorMessage = null;

    /**
     * Redirect non-admins; load current state and auto-list the modules granted
     * by the already-stored key (so the table is shown on page open).
     */
    public function mount(SettingsService $settings, LicenseClient $client): void
    {
        if (! Auth::user()?->isAdmin()) {
            $this->redirectRoute('admin.settings.general', navigate: true);

            return;
        }

        $this->hasKey = $settings->has('license.key');

        if ($this->hasKey) {
            $stored = (string) ($settings->get('license.key') ?? '');

            if ($stored !== '') {
                $this->lookup($client, $stored);
            }
        }
    }

    /**
     * Verify the license key against the License Server, list its modules, and
     * persist the key on success — the «Проверить» action.
     */
    public function check(SettingsService $settings, LicenseClient $client): void
    {
        if (! Auth::user()?->isAdmin()) {
            return;
        }

        // Resolve the key: the entered value, else the stored one.
        $entered = trim($this->license_key);
        $key = $entered !== '' ? $entered : (string) ($settings->get('license.key') ?? '');

        if ($key === '') {
            $this->errorMessage = 'Введите лицензионный ключ.';
            $this->checked = false;
            $this->products = [];

            return;
        }

        if (! $this->lookup($client, $key)) {
            return;
        }

        // Persist the key (and mirror to installed modules) only when a new value
        // was entered — a blank field reuses the already-stored key unchanged.
        if ($entered !== '') {
            $this->persist($settings, $entered);
        }

        $this->license_key = '';
        $this->hasKey = $settings->has('license.key');
    }

    /**
     * Fetch the modules a key grants and populate the table. Returns true on
     * success; on failure sets the error message and returns false.
     */
    private function lookup(LicenseClient $client, string $key): bool
    {
        $this->errorMessage = null;
        $this->checked = false;
        $this->products = [];

        $result = $client->products($key);

        if (! $result['success']) {
            $this->errorMessage = $result['message'];

            return false;
        }

        $this->products = $result['products'];
        $this->checked = true;

        return true;
    }

    /**
     * Store the shared key and mirror it into each installed module's own key,
     * dropping the module's cached license verdict so the new key takes effect.
     */
    private function persist(SettingsService $settings, string $key): void
    {
        $settings->set('license.key', $key);

        /** @var array<string, string> $moduleKeys */
        $moduleKeys = (array) config('license.module_keys', []);

        foreach ($moduleKeys as $slug => $settingKey) {
            $settings->set((string) $settingKey, $key);
            // Module LicenseGuard caches its verdict under "{slug}.license.state".
            Cache::forget($slug . '.license.state');
        }
    }

    /**
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.subscriptions-page');
    }
}
