<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\IntegrationChannelPage;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationChannelPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_channel_page(): void
    {
        $response = $this->get(route('admin.settings.integrations.channel', ['channel' => 'telegram']));

        $response->assertRedirectContains('/admin/login');
    }

    public function test_authenticated_admin_can_render_telegram_channel_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSuccessful();
    }

    // ── Route registration ────────────────────────────────────────────────────

    public function test_route_admin_settings_integrations_channel_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.integrations.channel'));
    }

    // ── Mount / initial state ────────────────────────────────────────────────

    public function test_mount_loads_telegram_fields_from_settings_service(): void
    {
        // telegram.group_id is no longer a field on this page — it moved to GeneralSettingsPage.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'tok123');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSet('channel', 'telegram')
            ->assertSet('telegram_token', 'tok123');
    }

    public function test_mount_sets_channel_connected_when_telegram_configured(): void
    {
        // Connected requires only token + secret_key — group_id is no longer part of the check.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'tok');
        $settings->set('telegram.secret_key', 'sec');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSet('channelConnected', true);
    }

    public function test_mount_sets_channel_not_connected_when_keys_absent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Remove settings seeded by TestCase::setUp() so the channel appears disconnected
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->forget('telegram.token');
        $settings->forget('telegram.secret_key');
        \Illuminate\Support\Facades\Cache::flush();

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSet('channelConnected', false);
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function test_renders_telegram_form_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSee('Токен бота')
            ->assertSee('Секретный ключ Webhook')
            // ID группы was moved to the «Основные» general settings page.
            ->assertDontSee('ID группы')
            ->assertDontSee('ID бота')
            ->assertDontSee('Бот AI помощника')
            ->assertSee('Сохранить');
    }

    public function test_renders_vk_form_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'vk'])
            ->assertSee('Токен')
            ->assertSee('Код подтверждения')
            ->assertSee('Сохранить');
    }

    public function test_renders_max_form_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'max'])
            ->assertSee('Токен')
            ->assertSee('Секретный ключ Webhook')
            ->assertSee('Сохранить');
    }

    public function test_renders_instruction_panel_for_telegram(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertSee('Инструкция')
            ->assertSee('Создайте группу в Telegram')
            ->assertSee('Добавьте бота как администратора')
            ->assertSee('Подробнее в документации');
    }

    public function test_renders_instruction_panel_for_vk(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'vk'])
            ->assertSee('Инструкция')
            ->assertSee('Создайте сообщество VK')
            ->assertSee('Подробнее в документации');
    }

    public function test_renders_instruction_panel_for_max(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'max'])
            ->assertSee('Инструкция')
            ->assertSee('Создайте бота в платформе MAX')
            ->assertSee('Подробнее в документации');
    }

    public function test_renders_channel_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->assertOk()
            ->assertSee('Telegram');
    }

    // ── Save — valid inputs ───────────────────────────────────────────────────

    public function test_save_telegram_persists_token(): void
    {
        // telegram.group_id is no longer managed on this page — it moved to GeneralSettingsPage.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', 'newtok123')
            ->set('telegram_secret_key', 'newsec123')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSet('formErrors', []);

        $this->assertDatabaseHas('settings', ['key' => 'telegram.token']);
    }

    public function test_save_telegram_does_not_overwrite_token_when_field_empty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Pre-set a token in DB
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'existing_tok');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', '')       // blank — should NOT overwrite
            ->call('save')
            ->assertSet('saved', true);

        // The pre-existing token must still be in DB (as encrypted value)
        $this->assertDatabaseHas('settings', ['key' => 'telegram.token']);
    }

    public function test_save_vk_persists_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'vk'])
            ->set('vk_token', 'vk_tok')
            ->set('vk_secret_key', 'sec')
            ->set('vk_confirm_code', 'code')
            ->call('save')
            ->assertSet('saved', true);

        // Secrets stored encrypted — key must exist
        $this->assertDatabaseHas('settings', ['key' => 'vk.token']);
        $this->assertDatabaseHas('settings', ['key' => 'vk.secret_key']);
        $this->assertDatabaseHas('settings', ['key' => 'vk.confirm_code']);
    }

    public function test_save_max_persists_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'max'])
            ->set('max_token', 'max_tok')
            ->set('max_secret_key', 'max_sec')
            ->call('save')
            ->assertSet('saved', true);

        $this->assertDatabaseHas('settings', ['key' => 'max.token']);
        $this->assertDatabaseHas('settings', ['key' => 'max.secret_key']);
    }

    // ── Save — no required-field validation errors remain for telegram on this page ──

    // ── Connect (save + webhook) ──────────────────────────────────────────────

    public function test_connect_saves_and_registers_webhook_for_telegram(): void
    {
        // Both getMe (verify) and setWebhook (register) return ok=true.
        // telegram.group_id is no longer part of verify — only token is checked.
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', 'bot123:validtoken')
            ->set('telegram_secret_key', 'secret')
            ->call('connect')
            ->assertSet('saved', true)
            ->assertSet('webhookSuccess', true);
    }

    public function test_connect_does_not_register_webhook_when_save_fails(): void
    {
        // Validation fails when both required fields are blank.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Ensure no stored token fallback exists.
        $settings = app(SettingsService::class);
        $settings->forget('telegram.token');
        $settings->forget('telegram.secret_key');
        \Illuminate\Support\Facades\Cache::flush();

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', '')
            ->set('telegram_secret_key', '')
            ->call('connect')
            ->assertSet('saved', false)
            ->assertSet('webhookSuccess', false);
    }

    public function test_connect_does_not_save_when_token_verification_fails(): void
    {
        // getMe returns ok=false — verification fails → the entered token must NOT be persisted.
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        // Store a sentinel value that must remain unchanged after the failed connect.
        $settings->set('telegram.token', 'original_token');
        $settings->set('telegram.secret_key', 'sec');
        \Illuminate\Support\Facades\Cache::flush();

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', 'bad_token')  // different value — must NOT be saved
            ->call('connect')
            ->assertSet('saved', false)
            ->assertSet('webhookSuccess', false);

        // The original stored token must still be the sentinel (not overwritten with 'bad_token').
        $stored = app(SettingsService::class)->get('telegram.token');
        $this->assertNotSame('bad_token', $stored);
    }

    // ── Verify-before-save — VK ───────────────────────────────────────────────

    public function test_connect_vk_does_not_save_when_token_verification_fails(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'error' => ['error_code' => 5, 'error_msg' => 'User authorization failed'],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('vk.token', 'original_vk_token');
        \Illuminate\Support\Facades\Cache::flush();

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'vk'])
            ->set('vk_token', 'bad_vk_token')  // must NOT be saved
            ->call('connect')
            ->assertSet('saved', false)
            ->assertSet('webhookSuccess', false);

        // The original stored token must still be the sentinel (not overwritten).
        $stored = app(SettingsService::class)->get('vk.token');
        $this->assertNotSame('bad_vk_token', $stored);
    }

    public function test_connect_vk_saves_when_verification_succeeds(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'response' => [['id' => 1, 'name' => 'Test Group']],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'vk'])
            ->set('vk_token', 'vk1.a.valid_token')
            ->call('connect')
            ->assertSet('saved', true)
            ->assertSet('webhookSuccess', true);

        $this->assertDatabaseHas('settings', ['key' => 'vk.token']);
    }

    // ── Verify-before-save — stored token fallback ────────────────────────────

    public function test_connect_telegram_uses_stored_token_when_form_is_blank(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'stored_token');
        $settings->set('telegram.secret_key', 'sec');
        \Illuminate\Support\Facades\Cache::flush();

        // Leave telegram_token blank — should fall back to stored token for verification.
        // telegram_group_id is no longer a field on this page.
        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->call('connect')
            ->assertSet('saved', true)
            ->assertSet('webhookSuccess', true);
    }

    public function test_connect_telegram_blocks_when_required_field_blank(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Ensure no telegram token is stored, then leave the field blank.
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->forget('telegram.token');
        \Illuminate\Support\Facades\Cache::flush();

        $component = Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->set('telegram_token', '')
            ->call('connect')
            ->assertSet('saved', false)
            ->assertSet('webhookSuccess', false);

        // Required-field validation blocks the save before verification.
        $this->assertArrayHasKey('telegram_token', $component->get('formErrors'));
    }

    // ── Webhook registration (standalone, backward-compat) ────────────────────

    public function test_register_webhook_telegram_success(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'description' => 'Webhook set'], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'bot123:validtoken');
        $settings->set('telegram.secret_key', 'secret');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->call('registerWebhook')
            ->assertSet('webhookSuccess', true);
    }

    public function test_register_webhook_telegram_error_on_api_failure(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'bad_token');
        $settings->set('telegram.secret_key', 'sec');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->call('registerWebhook')
            ->assertSet('webhookSuccess', false);
    }

    public function test_register_webhook_max_success(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('max.token', 'max_token');

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'max'])
            ->call('registerWebhook')
            ->assertSet('webhookSuccess', true);
    }

    public function test_register_webhook_shows_error_when_token_not_set(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->call('registerWebhook')
            ->assertSet('webhookSuccess', false);

        $component = Livewire::test(IntegrationChannelPage::class, ['channel' => 'telegram'])
            ->call('registerWebhook');

        $this->assertNotEmpty($component->get('webhookMessage'));
    }
}
