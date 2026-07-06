<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Bridges Avito API credentials from the host settings store into the paid
 * module's config tree.
 *
 * The prog-time/tg-support-avito module reads its API credentials exclusively
 * via config('avito.*') (see AvitoMethods / AvitoQuery), with no awareness of
 * the host SettingsService. The admin UI, however, stores those credentials in
 * the `settings` DB table. This provider closes that gap: at boot — after the
 * module's register() merged its config defaults — it overrides the relevant
 * config('avito.*') values with the DB values whenever they are present.
 *
 * Only non-empty stored values override the module defaults, so an unconfigured
 * key keeps the module's own env/default fallback (e.g. base_url → api.avito.ru).
 *
 * No-op when the module is not installed, so it costs nothing on deployments
 * without Avito. The license key is NOT bridged here — the module's LicenseGuard
 * already reads it straight from SettingsService.
 */
class AvitoSettingsBridgeServiceProvider extends ServiceProvider
{
    /** Map of host setting key → module config path. */
    private const MAP = [
        'avito.client_id' => 'avito.client_id',
        'avito.client_secret' => 'avito.client_secret',
        'avito.user_id' => 'avito.user_id',
        'avito.base_url' => 'avito.base_url',
        'avito.webhook_secret' => 'avito.webhook_secret',
    ];

    /**
     * Push stored Avito API credentials into the module config at boot.
     */
    public function boot(SettingsService $settings): void
    {
        // Module absent → nothing to feed; skip the DB reads entirely.
        if (! class_exists(\ProgTime\TgSupportAvito\AvitoServiceProvider::class)) {
            return;
        }

        foreach (self::MAP as $settingKey => $configPath) {
            try {
                $value = $settings->get($settingKey);
            } catch (Throwable) {
                // Settings store unavailable (e.g. DB not migrated) — leave the
                // module's own config/env fallback in place.
                return;
            }

            if (is_string($value) && $value !== '') {
                config([$configPath => $value]);
            }
        }
    }
}
