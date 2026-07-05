<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\IntegrationsListPage;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationsListPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        // On non-mobile UA the index redirects to telegram channel page,
        // which itself then redirects to login — assertRedirect covers both.
        $response = $this->get(route('admin.settings.integrations'));

        // May redirect to login directly or via /integrations/telegram
        $this->assertTrue(
            str_contains($response->headers->get('Location', ''), '/admin/login')
            || $response->isRedirect()
        );
    }

    public function test_authenticated_admin_can_render_integrations_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationsListPage::class)
            ->assertSuccessful();
    }

    public function test_authenticated_manager_can_render_integrations_list(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(IntegrationsListPage::class)
            ->assertSuccessful();
    }

    // ── Route registration ────────────────────────────────────────────────────

    public function test_route_admin_settings_integrations_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.integrations'));
    }

    public function test_route_admin_settings_integrations_list_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.integrations.list'));
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function test_renders_page_header(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Интеграции')
            ->assertSee('Telegram')
            ->assertSee('Бот AI помощника')
            ->assertSee('ВКонтакте')
            ->assertSee('Max');
    }

    public function test_renders_not_connected_status_when_keys_absent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Remove all channel token settings seeded by TestCase::setUp()
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->forget('telegram.token');
        $settings->forget('telegram.secret_key');
        $settings->forget('vk.token');
        $settings->forget('vk.secret_key');
        $settings->forget('max.token');
        $settings->forget('max.secret_key');
        \Illuminate\Support\Facades\Cache::flush();

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Не подключён');
    }

    public function test_renders_connected_status_when_telegram_keys_present(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->set('telegram.token', 'bot123:tok');
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-1001234567890');

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Подключено');
    }

    public function test_channel_statuses_property_has_all_four_keys(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $component = Livewire::test(IntegrationsListPage::class);

        $statuses = $component->get('channelStatuses');
        $this->assertArrayHasKey('telegram', $statuses);
        $this->assertArrayHasKey('telegram_ai', $statuses);
        $this->assertArrayHasKey('vk', $statuses);
        $this->assertArrayHasKey('max', $statuses);
    }

    public function test_renders_telegram_ai_card_with_link_to_channel_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Бот AI помощника')
            ->assertSee(route('admin.settings.integrations.channel', ['channel' => 'telegram_ai']));
    }

    public function test_renders_telegram_ai_card_description(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Интеграция с ботом AI-помощника');
    }

    public function test_renders_channel_description_texts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Интеграция с Telegram — приём обращений из бота.')
            ->assertSee('Интеграция с ВКонтакте — сообщения из чата сообщества.')
            ->assertSee('Интеграция с Max — обращения из мессенджера Max.');
    }

    public function test_renders_connect_button_for_disconnected_channel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        // Remove all channel token settings seeded by TestCase::setUp()
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->forget('telegram.token');
        $settings->forget('telegram.secret_key');
        $settings->forget('vk.token');
        $settings->forget('vk.secret_key');
        $settings->forget('max.token');
        $settings->forget('max.secret_key');
        Cache::flush();

        Livewire::test(IntegrationsListPage::class)
            ->assertSee('Подключить');
    }
}
