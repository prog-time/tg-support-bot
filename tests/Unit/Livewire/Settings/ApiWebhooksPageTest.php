<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\ApiWebhooksPage;
use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiWebhooksPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.settings.api-webhooks'));

        $response->assertRedirectContains('/admin/login');
    }

    public function test_authenticated_admin_can_render_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSuccessful();
    }

    public function test_non_admin_manager_is_redirected_on_mount(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ApiWebhooksPage::class)
            ->assertRedirect(route('admin.settings.general'));
    }

    // ── Route registration ─────────────────────────────────────────────────────

    public function test_route_admin_settings_api_webhooks_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.api-webhooks'));
    }

    // ── Mount / rendering ──────────────────────────────────────────────────────

    public function test_renders_page_title(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('API и вебхуки')
            ->assertSee('Управление API-ключами');
    }

    public function test_renders_empty_state_when_no_sources(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('Источников пока нет');
    }

    public function test_renders_sources_on_mount(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create([
            'name' => 'Test Source',
            'webhook_url' => 'https://example.com/hook',
        ]);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('Test Source')
            ->assertSee(route('admin.settings.api-webhooks.source', $source->id));
    }

    // ── Add source ──────────────────────────────────────────────────────────────

    public function test_renders_add_source_button(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('Добавить источник');
    }

    public function test_add_source_creates_source_with_placeholder_name_and_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->call('addSource')
            ->assertRedirect();

        $source = ExternalSource::first();
        $this->assertNotNull($source);
        $this->assertSame('Новый источник', $source->name);
        $this->assertDatabaseHas('external_source_access_tokens', [
            'external_source_id' => $source->id,
            'active' => true,
        ]);
    }

    public function test_add_source_with_widget_type_creates_public_key_not_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->call('addSource', ExternalSource::TYPE_WIDGET)
            ->assertRedirect();

        $source = ExternalSource::first();
        $this->assertNotNull($source);
        $this->assertSame('Живой чат', $source->name);
        $this->assertSame(ExternalSource::TYPE_WIDGET, $source->type);
        $this->assertNotNull($source->public_key);
        $this->assertDatabaseMissing('external_source_access_tokens', [
            'external_source_id' => $source->id,
        ]);
    }

    public function test_add_source_with_invalid_type_falls_back_to_api(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->call('addSource', 'bogus-type')
            ->assertRedirect();

        $source = ExternalSource::first();
        $this->assertSame(ExternalSource::TYPE_API, $source->type);
    }

    public function test_type_choice_modal_is_rendered(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('API источник')
            ->assertSee('Живой чат');
    }

    public function test_renders_widget_badge_for_widget_source(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        ExternalSource::factory()->create([
            'name' => 'My Widget',
            'type' => ExternalSource::TYPE_WIDGET,
        ]);

        Livewire::test(ApiWebhooksPage::class)
            ->assertSee('My Widget')
            ->assertSee('Виджет — см. публичный ключ');
    }

    public function test_add_source_generates_unique_placeholder_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        ExternalSource::factory()->create(['name' => 'Новый источник']);

        Livewire::test(ApiWebhooksPage::class)
            ->call('addSource')
            ->assertRedirect();

        $this->assertDatabaseHas('external_sources', ['name' => 'Новый источник 2']);
    }

    public function test_delete_source_removes_only_the_targeted_source(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $alpha = ExternalSource::factory()->create(['name' => 'Alpha']);
        $beta = ExternalSource::factory()->create(['name' => 'Beta']);

        Livewire::test(ApiWebhooksPage::class)
            ->call('deleteSource', $alpha->id)
            ->assertDontSee('Alpha')
            ->assertSee('Beta');

        $this->assertDatabaseMissing('external_sources', ['id' => $alpha->id]);
        $this->assertDatabaseHas('external_sources', ['id' => $beta->id]);
    }

    // ── ExternalSource model helper ───────────────────────────────────────────

    public function test_active_token_helper_returns_active_token(): void
    {
        $source = ExternalSource::factory()->create(['name' => 'Source M']);

        ExternalSourceAccessTokens::factory()->create([
            'external_source_id' => $source->id,
            'token' => str_repeat('z', 64),
            'active' => true,
        ]);

        $activeToken = $source->activeToken();
        $this->assertNotNull($activeToken);
        $this->assertTrue($activeToken->active);
    }

    public function test_active_token_helper_returns_null_when_inactive(): void
    {
        $source = ExternalSource::factory()->create(['name' => 'Source N']);

        ExternalSourceAccessTokens::factory()->create([
            'external_source_id' => $source->id,
            'token' => str_repeat('w', 64),
            'active' => false,
        ]);

        $this->assertNull($source->activeToken());
    }
}
