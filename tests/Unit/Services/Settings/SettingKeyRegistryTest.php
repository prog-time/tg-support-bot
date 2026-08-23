<?php

namespace Tests\Unit\Services\Settings;

use App\Services\Settings\SettingKeyRegistry;
use Tests\TestCase;

class SettingKeyRegistryTest extends TestCase
{
    // ── meta() for known keys ────────────────────────────────────────────────

    public function test_meta_returns_metadata_for_a_known_key(): void
    {
        $meta = SettingKeyRegistry::meta('telegram.token');

        $this->assertSame('string', $meta['type']);
        $this->assertNull($meta['config']);
        $this->assertTrue($meta['is_secret']);
    }

    public function test_meta_marks_secret_keys_as_secret(): void
    {
        $this->assertTrue(SettingKeyRegistry::meta('telegram.token')['is_secret']);
        $this->assertTrue(SettingKeyRegistry::meta('vk.token')['is_secret']);
        $this->assertTrue(SettingKeyRegistry::meta('ai.openai_api_key')['is_secret']);
        $this->assertTrue(SettingKeyRegistry::meta('ai.gigachat_client_secret')['is_secret']);
        $this->assertTrue(SettingKeyRegistry::meta('webpush.vapid_private_key')['is_secret']);
    }

    public function test_meta_does_not_mark_non_secret_keys_as_secret(): void
    {
        $this->assertFalse(SettingKeyRegistry::meta('telegram.group_id')['is_secret']);
        $this->assertFalse(SettingKeyRegistry::meta('ai.enabled')['is_secret']);
        $this->assertFalse(SettingKeyRegistry::meta('ai.default_provider')['is_secret']);
        $this->assertFalse(SettingKeyRegistry::meta('webpush.vapid_public_key')['is_secret']);
        $this->assertFalse(SettingKeyRegistry::meta('webpush.vapid_subject')['is_secret']);
    }

    public function test_meta_carries_the_typed_keys(): void
    {
        $this->assertSame('bool', SettingKeyRegistry::meta('ai.enabled')['type']);
        $this->assertSame('int', SettingKeyRegistry::meta('ai.max_context_tokens')['type']);
        $this->assertSame('string', SettingKeyRegistry::meta('max.token')['type']);
    }

    public function test_in_scope_keys_have_null_config_fallback(): void
    {
        // All channel/AI credentials have config=>null (no env fallback — DB only)
        $this->assertNull(SettingKeyRegistry::meta('telegram.token')['config']);
        $this->assertNull(SettingKeyRegistry::meta('telegram.secret_key')['config']);
        $this->assertNull(SettingKeyRegistry::meta('telegram.group_id')['config']);
        $this->assertNull(SettingKeyRegistry::meta('telegram_ai.token')['config']);
        $this->assertNull(SettingKeyRegistry::meta('telegram_ai.secret')['config']);
        $this->assertNull(SettingKeyRegistry::meta('vk.token')['config']);
        $this->assertNull(SettingKeyRegistry::meta('vk.secret_key')['config']);
        $this->assertNull(SettingKeyRegistry::meta('max.token')['config']);
        $this->assertNull(SettingKeyRegistry::meta('ai.openai_api_key')['config']);
        $this->assertNull(SettingKeyRegistry::meta('ai.enabled')['config']);
        $this->assertNull(SettingKeyRegistry::meta('ai.default_provider')['config']);
        $this->assertNull(SettingKeyRegistry::meta('ai.confidence_threshold')['config']);
    }

    // ── meta() for unknown keys ──────────────────────────────────────────────

    public function test_meta_returns_safe_defaults_for_an_unknown_key(): void
    {
        $meta = SettingKeyRegistry::meta('totally.unknown.key');

        $this->assertSame('string', $meta['type']);
        $this->assertNull($meta['config']);
        $this->assertFalse($meta['is_secret']);
    }

    // ── keys() ───────────────────────────────────────────────────────────────

    public function test_keys_returns_all_registered_keys(): void
    {
        $keys = SettingKeyRegistry::keys();

        $this->assertNotContains('app.manager_interface', $keys);
        $this->assertContains('telegram.token', $keys);
        $this->assertContains('vk.confirm_code', $keys);
        $this->assertContains('max.secret_key', $keys);
        $this->assertContains('ai.default_provider', $keys);
        $this->assertContains('ai.confidence_threshold', $keys);
        $this->assertContains('ai.auto_escalation', $keys);
        $this->assertContains('ai.enable_logging', $keys);
        $this->assertNotContains('ai.rate_limit.requests_per_minute', $keys);
        $this->assertNotContains('ai.rate_limit.requests_per_hour', $keys);
        $this->assertNotContains('ai.disable_timeout', $keys);
    }

    public function test_keys_returns_a_list_of_unique_strings(): void
    {
        $keys = SettingKeyRegistry::keys();

        $this->assertSame(array_values($keys), $keys);
        $this->assertSame(array_unique($keys), $keys);
        $this->assertContainsOnly('string', $keys);
    }

    // ── registered() ──────────────────────────────────────────────────────────

    public function test_registered_is_false_for_removed_manager_interface_key(): void
    {
        $this->assertFalse(SettingKeyRegistry::registered('app.manager_interface'));
    }

    public function test_registered_is_true_for_a_known_key(): void
    {
        $this->assertTrue(SettingKeyRegistry::registered('telegram.token'));
    }

    public function test_registered_is_false_for_an_unknown_key(): void
    {
        $this->assertFalse(SettingKeyRegistry::registered('totally.unknown.key'));
    }

    public function test_every_registered_key_has_consistent_metadata_shape(): void
    {
        foreach (SettingKeyRegistry::keys() as $key) {
            $meta = SettingKeyRegistry::meta($key);

            $this->assertArrayHasKey('type', $meta);
            $this->assertArrayHasKey('config', $meta);
            $this->assertArrayHasKey('is_secret', $meta);
            $this->assertContains($meta['type'], ['string', 'bool', 'int', 'json']);
            $this->assertTrue(SettingKeyRegistry::registered($key));
        }
    }
}
