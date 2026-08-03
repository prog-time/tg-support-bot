<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\ApiWebhookSourcePage;
use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiWebhookSourcePageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $response = $this->get(route('admin.settings.api-webhooks.source', $source->id));

        $response->assertRedirectContains('/admin/login');
    }

    public function test_authenticated_admin_can_render_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSuccessful();
    }

    public function test_non_admin_is_redirected_on_mount(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertRedirect(route('admin.settings.general'));
    }

    public function test_missing_source_redirects_to_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => 99999])
            ->assertRedirect(route('admin.settings.api-webhooks'));
    }

    // ── Rendering ──────────────────────────────────────────────────────────────

    public function test_renders_source_name_and_key_sections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'My Integration']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSee('My Integration')
            ->assertSee('Ключ API')
            ->assertSee('URL вебхука')
            ->assertSee('Разрешённые IP-адреса')
            ->assertSee('REST API')
            ->assertSee('Swagger')
            // Removed sections must no longer render.
            ->assertDontSee('Секретный ключ')
            ->assertDontSee('События');
    }

    public function test_renders_masked_token_when_token_exists(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);
        ExternalSourceAccessTokens::factory()->create([
            'external_source_id' => $source->id,
            'token' => str_repeat('a', 58) . 'XYZ012',
            'active' => true,
        ]);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSee('XYZ012');
    }

    public function test_renders_no_token_placeholder_when_no_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSee('токен не выпущен');
    }

    // ── Widget (live-chat) sources ──────────────────────────────────────────────

    public function test_widget_source_renders_public_key_fields_not_api_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->widget()->create(['name' => 'My Widget']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSee('My Widget')
            ->assertSee('Живой чат')
            ->assertSee('Публичный ключ виджета')
            ->assertSee('Разрешённые домены')
            ->assertDontSee('URL вебхука')
            ->assertDontSee('Ключ API')
            ->assertDontSee('REST API');
    }

    public function test_api_source_does_not_render_widget_only_panel_copy(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'My API']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertDontSee('Публичный ключ виджета')
            ->assertDontSee('Код для вставки на сайт');
    }

    public function test_widget_source_does_not_render_ip_only_allowlist_label(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->widget()->create(['name' => 'My Widget']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertDontSee('Разрешённые IP-адреса');
    }

    public function test_api_source_does_not_render_domain_only_allowlist_label(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'My API']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertDontSee('Разрешённые домены');
    }

    public function test_widget_source_save_notice_does_not_mention_webhook(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->widget()->create(['name' => 'My Widget']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->call('saveWebhookUrl')
            ->assertSet('saved', true)
            ->assertSee('Настройки сохранены.')
            ->assertDontSee('URL вебхука сохранён.');
    }

    public function test_generate_public_key_on_widget_source_shows_embed_snippet(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->widget()->create(['name' => 'My Widget']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->call('generatePublicKey');

        $newKey = $component->get('newPublicKey');
        $this->assertNotNull($newKey);
        $this->assertStringStartsWith('pub_', $newKey);

        $component->assertSee('Код для вставки на сайт')
            ->assertSee('data-key="' . $source->fresh()->public_key . '"', false);
    }

    // ── Token regeneration ─────────────────────────────────────────────────────

    public function test_regenerate_token_creates_record_and_sets_new_token_prop(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->call('regenerateToken');

        $this->assertDatabaseHas('external_source_access_tokens', [
            'external_source_id' => $source->id,
            'active' => true,
        ]);

        $newToken = $component->get('newToken');
        $this->assertNotNull($newToken);
        $this->assertSame(64, strlen($newToken));
    }

    public function test_regenerate_token_replaces_existing_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);
        ExternalSourceAccessTokens::factory()->create([
            'external_source_id' => $source->id,
            'token' => str_repeat('a', 64),
            'active' => true,
        ]);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->call('regenerateToken');

        // Still only one record (upsert behaviour).
        $this->assertSame(1, ExternalSourceAccessTokens::where('external_source_id', $source->id)->count());

        // Token value changed.
        $tokenRecord = ExternalSourceAccessTokens::where('external_source_id', $source->id)->first();
        $this->assertNotEquals(str_repeat('a', 64), $tokenRecord->token);

        // New token surfaced in prop.
        $newToken = $component->get('newToken');
        $this->assertSame(64, strlen($newToken));
    }

    public function test_dismiss_new_token_clears_reveal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->call('regenerateToken')
            ->call('dismissNewToken');

        $this->assertNull($component->get('newToken'));
    }

    // ── Webhook URL ────────────────────────────────────────────────────────────

    public function test_save_webhook_url_persists_valid_url(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create([
            'name' => 'Source',
            'webhook_url' => null,
        ]);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('webhookUrl', 'https://example.com/webhook')
            ->call('saveWebhookUrl')
            ->assertSuccessful();

        $this->assertDatabaseHas('external_sources', [
            'id' => $source->id,
            'webhook_url' => 'https://example.com/webhook',
        ]);
    }

    public function test_save_webhook_url_clears_on_empty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create([
            'name' => 'Source',
            'webhook_url' => 'https://old.example.com/hook',
        ]);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('webhookUrl', '')
            ->call('saveWebhookUrl')
            ->assertSuccessful();

        $this->assertDatabaseHas('external_sources', [
            'id' => $source->id,
            'webhook_url' => null,
        ]);
    }

    public function test_save_webhook_url_rejects_invalid_url(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('webhookUrl', 'not-a-valid-url')
            ->call('saveWebhookUrl');

        $this->assertNotNull($component->get('webhookError'));

        $this->assertDatabaseMissing('external_sources', [
            'id' => $source->id,
            'webhook_url' => 'not-a-valid-url',
        ]);
    }

    public function test_save_updates_source_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Новый источник']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('sourceName', 'CRM')
            ->call('saveWebhookUrl')
            ->assertSet('saved', true)
            ->assertSet('sourceName', 'CRM');

        $this->assertDatabaseHas('external_sources', ['id' => $source->id, 'name' => 'CRM']);
    }

    public function test_save_rejects_empty_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'CRM']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('sourceName', '   ')
            ->call('saveWebhookUrl');

        $this->assertNotNull($component->get('nameError'));
        $this->assertDatabaseHas('external_sources', ['id' => $source->id, 'name' => 'CRM']);
    }

    public function test_save_rejects_duplicate_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        ExternalSource::factory()->create(['name' => 'Taken']);
        $source = ExternalSource::factory()->create(['name' => 'Mine']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('sourceName', 'Taken')
            ->call('saveWebhookUrl');

        $this->assertNotNull($component->get('nameError'));
        $this->assertDatabaseHas('external_sources', ['id' => $source->id, 'name' => 'Mine']);
    }

    public function test_save_webhook_url_sets_saved_flag(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('webhookUrl', 'https://example.com/hook')
            ->call('saveWebhookUrl');

        $this->assertTrue($component->get('saved'));
    }

    // ── Allowed IPs ─────────────────────────────────────────────────────────────

    public function test_mount_loads_allowed_ips_as_lines(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create([
            'name' => 'Source',
            'allowed_ips' => ['203.0.113.10', '198.51.100.5'],
        ]);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->assertSet('allowedIps', "203.0.113.10\n198.51.100.5");
    }

    public function test_save_persists_allowed_ips_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('webhookUrl', '')
            ->set('allowedIps', "203.0.113.10\n198.51.100.5\n203.0.113.10")
            ->call('saveWebhookUrl')
            ->assertSuccessful()
            ->assertSet('saved', true);

        // Deduplicated, two unique IPs persisted.
        $this->assertSame(
            ['203.0.113.10', '198.51.100.5'],
            ExternalSource::find($source->id)->allowed_ips
        );
    }

    public function test_save_rejects_invalid_ip(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create(['name' => 'Source']);

        $component = Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('allowedIps', "203.0.113.10\nnot-an-ip")
            ->call('saveWebhookUrl');

        $this->assertNotNull($component->get('allowedIpsError'));
        $this->assertFalse($component->get('saved'));
        $this->assertNull(ExternalSource::find($source->id)->allowed_ips);
    }

    public function test_empty_allowed_ips_saved_as_null(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $source = ExternalSource::factory()->create([
            'name' => 'Source',
            'allowed_ips' => ['203.0.113.10'],
        ]);

        Livewire::test(ApiWebhookSourcePage::class, ['source' => $source->id])
            ->set('allowedIps', '')
            ->call('saveWebhookUrl')
            ->assertSet('saved', true);

        $this->assertNull(ExternalSource::find($source->id)->allowed_ips);
    }
}
