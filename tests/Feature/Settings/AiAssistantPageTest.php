<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\AiAssistantPage;
use App\Models\AiFaqEntry;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.settings.ai'));

        $response->assertRedirectContains('/admin/login');
    }

    public function test_authenticated_admin_can_render_ai_assistant_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(AiAssistantPage::class)
            ->assertSuccessful();
    }

    // ── Route registration ────────────────────────────────────────────────────

    public function test_route_admin_settings_ai_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.ai'));
    }

    // ── Mount / initial state ────────────────────────────────────────────────

    public function test_mount_loads_values_from_settings_service(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('ai.enabled', true);
        $settings->set('ai.default_provider', 'deepseek');
        // deepseek must have access configured to remain the active provider.
        $settings->set('ai.deepseek_client_secret', 'secret');
        $settings->set('ai.auto_reply', false);
        $settings->set('ai.system_prompt', 'Be helpful.');

        Livewire::test(AiAssistantPage::class)
            ->assertSet('ai_enabled', true)
            ->assertSet('default_provider', 'deepseek')
            ->assertSet('auto_reply', false)
            ->assertSet('system_prompt', 'Be helpful.');
    }

    public function test_mount_uses_defaults_when_no_settings_stored(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        config([
            'ai.enabled' => false,
            'ai.default_provider' => 'openai',
            'ai.auto_reply' => false,
        ]);

        Livewire::test(AiAssistantPage::class)
            ->assertSet('ai_enabled', false)
            // No provider configured → none is pre-selected.
            ->assertSet('default_provider', '')
            ->assertSet('auto_reply', false);
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    public function test_save_persists_all_ai_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // The chosen provider must have its access credentials configured,
        // otherwise save() rejects it as «Доступы не указаны».
        app(SettingsService::class)->set('ai.gigachat_client_secret', 'secret');

        Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', true)
            ->set('default_provider', 'gigachat')
            ->set('auto_reply', false)
            ->set('system_prompt', 'My prompt')
            ->call('save')
            ->assertSet('saved', true)
            ->assertHasNoErrors();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $this->assertTrue((bool) $settings->get('ai.enabled'));
        $this->assertSame('gigachat', (string) $settings->get('ai.default_provider'));
        // The prompt is stored in the DB (single source of truth).
        $this->assertSame('My prompt', (string) $settings->get('ai.system_prompt'));
    }

    public function test_save_rejects_invalid_provider(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $component = Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', true)
            ->set('default_provider', 'anthropic')
            ->call('save')
            ->assertSet('saved', false);

        $this->assertArrayHasKey('default_provider', $component->get('formErrors'));
    }

    // ── Provider access gating ─────────────────────────────────────────────────

    public function test_save_rejects_provider_without_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // gigachat has no credentials configured → it cannot be selected.
        $component = Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', true)
            ->set('default_provider', 'gigachat')
            ->call('save')
            ->assertSet('saved', false);

        $this->assertArrayHasKey('default_provider', $component->get('formErrors'));
        $this->assertSame(
            'У выбранного провайдера не указаны доступы.',
            $component->get('formErrors')['default_provider'],
        );
    }

    public function test_provider_without_access_shows_flag_and_no_select_button(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        // openai is the active, configured provider; deepseek/gigachat are not.
        $settings->set('ai.enabled', true);
        $settings->set('ai.default_provider', 'openai');
        $settings->set('ai.openai_api_key', 'sk-test');

        Livewire::test(AiAssistantPage::class)
            ->assertSee('Доступы не указаны')
            ->assertSet('providerConfigured.openai', true)
            ->assertSet('providerConfigured.deepseek', false)
            ->assertSet('providerConfigured.gigachat', false);
    }

    public function test_no_provider_is_preselected_when_none_has_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Nothing configured at all — OpenAI must NOT be pre-selected.
        Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', true)
            ->assertSet('default_provider', '')
            ->assertDontSee('Активен');
    }

    public function test_stored_provider_without_access_falls_back_to_configured_one(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        // openai is stored as default but has no access; deepseek is configured.
        $settings->set('ai.default_provider', 'openai');
        $settings->set('ai.deepseek_client_secret', 'secret');

        Livewire::test(AiAssistantPage::class)
            ->assertSet('default_provider', 'deepseek');
    }

    // ── Master AI toggle (instant persist) ─────────────────────────────────────

    public function test_master_toggle_persists_without_save(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Toggling the master switch persists immediately — no save() call.
        Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', true);

        $this->assertTrue((bool) $settings->get('ai.enabled'));

        Livewire::test(AiAssistantPage::class)
            ->set('ai_enabled', false);

        $this->assertFalse((bool) $settings->get('ai.enabled'));
    }

    public function test_master_toggle_can_be_enabled_without_telegram_group(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->forget('telegram.group_id');

        Livewire::test(AiAssistantPage::class)
            ->assertDontSee('Бот AI помощника')
            ->set('ai_enabled', true)
            // Toggle stays enabled — no guard blocks it.
            ->assertSet('ai_enabled', true);

        $this->assertTrue((bool) $settings->get('ai.enabled'));
    }

    // ── Auto-reply confirm flow ───────────────────────────────────────────────

    public function test_auto_reply_toggle_shows_warning_instead_of_saving(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Setting auto_reply to true triggers the updatedAutoReply lifecycle hook
        Livewire::test(AiAssistantPage::class)
            ->set('auto_reply', true)
            ->assertSet('auto_reply', false)
            ->assertSet('showAutoReplyWarning', true);
    }

    public function test_confirm_auto_reply_enables_auto_reply(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(AiAssistantPage::class)
            ->set('auto_reply', true)
            ->call('confirmAutoReply')
            ->assertSet('auto_reply', true)
            ->assertSet('showAutoReplyWarning', false);
    }

    public function test_cancel_auto_reply_keeps_auto_reply_disabled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(AiAssistantPage::class)
            ->set('auto_reply', true)
            ->call('cancelAutoReply')
            ->assertSet('auto_reply', false)
            ->assertSet('showAutoReplyWarning', false);
    }

    // ── Clarify mode / FAQ ───────────────────────────────────────────────────

    public function test_save_persists_clarify_mode(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(AiAssistantPage::class)
            ->assertSet('clarify_mode', false)
            ->set('clarify_mode', true)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertTrue((bool) app(SettingsService::class)->get('ai.clarify_mode'));
    }

    public function test_page_shows_clarify_toggle_and_faq_link_with_enabled_count(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        app(SettingsService::class)->set('ai.enabled', true);
        AiFaqEntry::create(['question' => 'Q1', 'answer' => 'A1', 'enabled' => true]);
        AiFaqEntry::create(['question' => 'Q2', 'answer' => 'A2', 'enabled' => false]);

        Livewire::test(AiAssistantPage::class)
            ->assertSet('faqEnabledCount', 1)
            ->assertSee('Уточняющие вопросы (grill me)')
            ->assertSee('Частые вопросы')
            ->assertSee(route('admin.settings.ai.faq'));
    }
}
