<?php

namespace Tests;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $botToken;

    /**
     * Default Telegram group ID seeded into SettingsService for tests.
     */
    protected string $defaultGroupId = '-100000000000';

    protected function setUp(): void
    {
        parent::setUp();

        // Admin Blade layouts use @vite(...), which requires a built
        // public/build/manifest.json. CI runs the PHP test suite without
        // building front-end assets, so disable Vite for tests — @vite
        // directives resolve to empty strings instead of throwing
        // ViteManifestNotFoundException.
        $this->withoutVite();

        $this->botToken = '123:ABC';

        // Seed common settings into the DB-backed SettingsService.
        // Wrapped in a try/catch because some pure unit tests do not run
        // migrations (no RefreshDatabase) and the settings table may not exist.
        try {
            $settings = app(SettingsService::class);
            $settings->set('telegram.token', $this->botToken);
            $settings->set('telegram.group_id', $this->defaultGroupId);
            $settings->set('telegram.secret_key', 'test-secret-key');
            $settings->set('telegram.template_topic_name', '{first_name} {last_name} {platform}');
            $settings->set('vk.token', 'vk-test-token');
            $settings->set('vk.secret_key', env('TEST_VK_SECRET_CODE', 'test-vk-secret'));
            $settings->set('vk.confirm_code', 'vk-confirm-code');
            $settings->set('max.token', 'max-test-token');
            $settings->set('max.secret_key', env('TEST_MAX_SECRET_KEY', 'test-max-secret'));
            $settings->set('ai.enabled', false);
            $settings->set('ai.auto_reply', false);
            $settings->set('ai.default_provider', 'openai');
            $settings->set('ai.max_context_tokens', 3000);
            $settings->set('ai.confidence_threshold', '0.8');
            $settings->set('ai.rate_limit.requests_per_minute', 60);
            $settings->set('ai.rate_limit.requests_per_hour', 1000);
        } catch (\Throwable) {
            // Settings table not available in this test (no RefreshDatabase).
            // Individual tests that need settings must use RefreshDatabase.
        }
    }
}
