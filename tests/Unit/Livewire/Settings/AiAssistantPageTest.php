<?php

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\AiAssistantPage;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for AiAssistantPage Livewire component.
 *
 * Focuses on business logic: mount(), save(),
 * updatedAutoReply(), confirmAutoReply(), cancelAutoReply()
 * using a mocked SettingsService.
 */
class AiAssistantPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // ── mount ────────────────────────────────────────────────────────────────────

    public function test_mount_populates_properties_from_settings_service(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('ai.enabled')->andReturn(true);
        $mock->shouldReceive('get')->with('ai.default_provider')->andReturn('deepseek');
        $mock->shouldReceive('get')->with('ai.auto_reply')->andReturn(true);
        $mock->shouldReceive('get')->with('ai.clarify_mode')->andReturn(true);
        $mock->shouldReceive('get')->with('ai.system_prompt')->andReturn('Be helpful');
        $mock->shouldReceive('get')->with('ai.openai_api_key')->andReturn(null);
        // deepseek has access configured → it stays the active stored provider.
        $mock->shouldReceive('get')->with('ai.deepseek_client_secret')->andReturn('secret');
        $mock->shouldReceive('get')->with('ai.gigachat_client_secret')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.openai_model')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.deepseek_model')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.gigachat_model')->andReturn(null);

        $component = new AiAssistantPage();
        $component->mount($mock);

        $this->assertTrue($component->ai_enabled);
        $this->assertSame('deepseek', $component->default_provider);
        $this->assertTrue($component->auto_reply);
        $this->assertTrue($component->clarify_mode);
        $this->assertSame('Be helpful', $component->system_prompt);
    }

    public function test_mount_uses_defaults_when_settings_return_null(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('ai.enabled')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.default_provider')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.auto_reply')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.clarify_mode')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.system_prompt')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.openai_api_key')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.deepseek_client_secret')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.gigachat_client_secret')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.openai_model')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.deepseek_model')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.gigachat_model')->andReturn(null);

        $component = new AiAssistantPage();
        $component->mount($mock);

        $this->assertFalse($component->ai_enabled);
        // No provider has access configured → none is pre-selected.
        $this->assertSame('', $component->default_provider);
        $this->assertFalse($component->auto_reply);
        $this->assertFalse($component->clarify_mode);
        // No DB value → empty field (there is no default).
        $this->assertSame('', $component->system_prompt);
    }

    // ── save ─────────────────────────────────────────────────────────────────────

    public function test_save_persists_all_fields(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.system_prompt', 'Be concise')->once();
        $mock->shouldReceive('set')->with('ai.enabled', false)->once();
        $mock->shouldReceive('set')->with('ai.default_provider', 'gigachat')->once();
        $mock->shouldReceive('set')->with('ai.auto_reply', true)->once();
        $mock->shouldReceive('set')->with('ai.clarify_mode', true)->once();

        $component = new AiAssistantPage();
        $component->mount($mock);
        $component->default_provider = 'gigachat';
        $component->auto_reply = true;
        $component->clarify_mode = true;
        $component->system_prompt = 'Be concise';
        $component->save($mock);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_save_rejects_invalid_provider(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        // The prompt is saved independently; the toggle settings are NOT.
        $mock->shouldReceive('set')->with('ai.system_prompt', Mockery::any());
        $mock->shouldReceive('set')->with('ai.enabled', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.default_provider', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.auto_reply', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.clarify_mode', Mockery::any())->never();

        $component = new AiAssistantPage();
        $component->mount($mock);
        $component->ai_enabled = true;
        $component->default_provider = 'anthropic';
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('default_provider', $component->formErrors);
    }

    public function test_save_writes_prompt_even_when_provider_validation_fails(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        // The prompt persists regardless of the provider error...
        $mock->shouldReceive('set')->with('ai.system_prompt', 'Промпт должен сохраниться')->once();
        // ...but the toggle settings do not, because validation fails.
        $mock->shouldReceive('set')->with('ai.enabled', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.default_provider', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.auto_reply', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.clarify_mode', Mockery::any())->never();

        $component = new AiAssistantPage();
        $component->mount($mock);
        $component->ai_enabled = true;
        $component->default_provider = 'anthropic'; // invalid → blocks toggle save
        $component->system_prompt = 'Промпт должен сохраниться';
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('default_provider', $component->formErrors);
    }

    public function test_save_rejects_provider_without_access_when_enabled(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // No credentials for any provider.
        $mock->shouldReceive('get')->andReturn(null);
        // The prompt still saves; the toggle settings do not.
        $mock->shouldReceive('set')->with('ai.system_prompt', Mockery::any());
        $mock->shouldReceive('set')->with('ai.enabled', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.default_provider', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.auto_reply', Mockery::any())->never();
        $mock->shouldReceive('set')->with('ai.clarify_mode', Mockery::any())->never();

        $component = new AiAssistantPage();
        $component->mount($mock);
        $component->ai_enabled = true;
        $component->default_provider = 'openai';
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertSame(
            'У выбранного провайдера не указаны доступы.',
            $component->formErrors['default_provider'] ?? null,
        );
    }

    public function test_save_accepts_provider_with_access_when_enabled(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // Specific expectation first; Mockery matches in declaration order.
        $mock->shouldReceive('get')->with('ai.openai_api_key')->andReturn('sk-test');
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with(Mockery::any(), Mockery::any());

        $component = new AiAssistantPage();
        $component->mount($mock);
        $component->ai_enabled = true;
        $component->default_provider = 'openai';
        $component->save($mock);

        $this->assertTrue($component->saved);
        $this->assertArrayNotHasKey('default_provider', $component->formErrors);
    }

    public function test_mount_marks_provider_configured_from_credentials(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // Specific expectations first; Mockery matches in declaration order.
        $mock->shouldReceive('get')->with('ai.openai_api_key')->andReturn('sk-test');
        $mock->shouldReceive('get')->with('ai.gigachat_client_secret')->andReturn('secret');
        $mock->shouldReceive('get')->andReturn(null);

        $component = new AiAssistantPage();
        $component->mount($mock);

        $this->assertTrue($component->providerConfigured['openai']);
        $this->assertFalse($component->providerConfigured['deepseek']);
        $this->assertTrue($component->providerConfigured['gigachat']);
    }

    public function test_save_accepts_all_valid_providers(): void
    {
        foreach (['openai', 'deepseek', 'gigachat'] as $provider) {
            /** @var \Mockery\MockInterface&SettingsService $mock */
            $mock = Mockery::mock(SettingsService::class);
            $mock->shouldReceive('get')->andReturn(null);
            $mock->shouldReceive('set')->with(Mockery::any(), Mockery::any());

            $component = new AiAssistantPage();
            $component->mount($mock);
            $component->default_provider = $provider;
            $component->save($mock);

            $this->assertTrue($component->saved, "Provider {$provider} should be valid.");
            Mockery::close();
        }
    }

    // ── master AI toggle (instant persist) ────────────────────────────────────────

    public function test_updated_ai_enabled_persists_immediately(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('set')->with('ai.enabled', true)->once();

        // The hook resolves SettingsService from the container.
        $this->app->instance(SettingsService::class, $mock);

        $component = new AiAssistantPage();
        $component->aiBotConnected = true; // AI bot integration is configured
        $component->ai_enabled = true;     // simulate wire:model having set the value
        $component->updatedAiEnabled(true);

        // Stays enabled (not reverted) and persisted (mock ->once()).
        $this->assertTrue($component->ai_enabled);
    }

    public function test_updated_ai_enabled_persists_disable_immediately(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('set')->with('ai.enabled', false)->once();

        $this->app->instance(SettingsService::class, $mock);

        $component = new AiAssistantPage();
        $component->updatedAiEnabled(false);
    }

    public function test_updated_ai_enabled_allowed_without_ai_bot_connected(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // AI can always be enabled regardless of AI bot integration status.
        $mock->shouldReceive('set')->with('ai.enabled', true)->once();

        $this->app->instance(SettingsService::class, $mock);

        $component = new AiAssistantPage();
        $component->aiBotConnected = false; // AI bot integration not configured
        $component->ai_enabled = true;
        $component->updatedAiEnabled(true);

        // Should NOT revert — AI works without the AI bot (drafts appear in admin panel only).
        $this->assertTrue($component->ai_enabled);
    }

    // ── auto-reply confirm flow ───────────────────────────────────────────────────

    public function test_updated_auto_reply_true_shows_warning_and_reverts_toggle(): void
    {
        $component = new AiAssistantPage();
        $component->auto_reply = false;

        $component->updatedAutoReply(true);

        $this->assertFalse($component->auto_reply);
        $this->assertTrue($component->showAutoReplyWarning);
        $this->assertTrue($component->pendingAutoReply);
    }

    public function test_updated_auto_reply_false_clears_warning(): void
    {
        $component = new AiAssistantPage();
        $component->showAutoReplyWarning = true;
        $component->pendingAutoReply = true;

        $component->updatedAutoReply(false);

        $this->assertFalse($component->showAutoReplyWarning);
        $this->assertFalse($component->pendingAutoReply);
    }

    public function test_confirm_auto_reply_enables_and_clears_warning(): void
    {
        $component = new AiAssistantPage();
        $component->auto_reply = false;
        $component->showAutoReplyWarning = true;
        $component->pendingAutoReply = true;

        $component->confirmAutoReply();

        $this->assertTrue($component->auto_reply);
        $this->assertFalse($component->showAutoReplyWarning);
        $this->assertFalse($component->pendingAutoReply);
    }

    public function test_cancel_auto_reply_keeps_disabled_and_clears_warning(): void
    {
        $component = new AiAssistantPage();
        $component->auto_reply = false;
        $component->showAutoReplyWarning = true;
        $component->pendingAutoReply = true;

        $component->cancelAutoReply();

        $this->assertFalse($component->auto_reply);
        $this->assertFalse($component->showAutoReplyWarning);
        $this->assertFalse($component->pendingAutoReply);
    }
}
