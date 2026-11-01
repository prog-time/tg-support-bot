<?php

namespace Tests\Traits;

use App\Services\Settings\SettingsService;

/**
 * Helper trait for unit/feature tests that need to seed SettingsService values
 * before the code under test reads them.
 *
 * Because the config() fallback is now removed for all channel/AI keys,
 * tests must explicitly seed any settings value their code-path reads.
 *
 * Usage:
 *   use Tests\Traits\SeedsSettings;
 *   ...
 *   $this->seedSetting('telegram.group_id', '-100123456789');
 *   $this->seedSettings(['telegram.token' => 'tok:123', 'ai.enabled' => true]);
 */
trait SeedsSettings
{
    /**
     * Seed a single setting via SettingsService.
     *
     * @param string $key   Setting key in dot notation
     * @param mixed  $value Value to store
     *
     * @return void
     */
    protected function seedSetting(string $key, mixed $value): void
    {
        app(SettingsService::class)->set($key, $value);
    }

    /**
     * Seed multiple settings at once.
     *
     * @param array<string, mixed> $settings
     *
     * @return void
     */
    protected function seedSettings(array $settings): void
    {
        $service = app(SettingsService::class);
        foreach ($settings as $key => $value) {
            $service->set($key, $value);
        }
    }

    /**
     * Seed the default set of settings used by most tests.
     *
     * Matches the values previously available via phpunit.xml / .env test overrides.
     *
     * @param string $botToken Override the main bot token (default '123:ABC').
     *
     * @return void
     */
    protected function seedDefaultSettings(string $botToken = '123:ABC'): void
    {
        $this->seedSettings([
            'telegram.token' => $botToken,
            'telegram.group_id' => env('TELEGRAM_GROUP_ID', '-100000000000'),
            'telegram.secret_key' => 'test-secret-key',
            'telegram.template_topic_name' => '{first_name} {last_name} {platform}',
            'vk.token' => 'vk-test-token',
            'vk.secret_key' => env('TEST_VK_SECRET_CODE', 'test-vk-secret'),
            'vk.confirm_code' => 'vk-confirm-code',
            'max.token' => 'max-test-token',
            'max.secret_key' => env('TEST_MAX_SECRET_KEY', 'test-max-secret'),
            'ai.enabled' => false,
            'ai.auto_reply' => false,
            'ai.default_provider' => 'openai',
            'ai.max_context_tokens' => 3000,
            'ai.confidence_threshold' => '0.8',
            'ai.rate_limit.requests_per_minute' => 60,
            'ai.rate_limit.requests_per_hour' => 1000,
            'ai.disable_timeout' => '',
            'ai.auto_escalation' => true,
            'ai.enable_logging' => true,
        ]);
    }
}
