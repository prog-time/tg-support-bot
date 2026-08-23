<?php

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\GeneralSettingsPage;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for GeneralSettingsPage Livewire component.
 *
 * The screen manages:
 *   - telegram.template_topic_name — forum topic name template
 *   - telegram.group_id            — Telegram supergroup ID (moved here from the integration page)
 *
 * Bot name, description, and manager interface were removed from this screen.
 */
class GeneralSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // save() is admin-only; these tests exercise the admin save path.
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_mount_populates_template_from_settings_service(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('telegram.template_topic_name')->andReturn('Обращение');
        $mock->shouldReceive('get')->with('telegram.group_id')->andReturn('');
        $mock->shouldReceive('get')->with('webpush.vapid_public_key')->andReturn('');
        $component = new GeneralSettingsPage();
        $component->mount($mock);

        $this->assertSame('Обращение', $component->template_topic_name);
    }

    public function test_mount_populates_group_id_from_settings_service(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('telegram.template_topic_name')->andReturn('');
        $mock->shouldReceive('get')->with('telegram.group_id')->andReturn('-100999888');
        $mock->shouldReceive('get')->with('webpush.vapid_public_key')->andReturn('');
        $component = new GeneralSettingsPage();
        $component->mount($mock);

        $this->assertSame('-100999888', $component->group_id);
    }

    public function test_mount_uses_empty_string_when_settings_return_null(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('telegram.template_topic_name')->andReturn(null);
        $mock->shouldReceive('get')->with('telegram.group_id')->andReturn(null);
        $mock->shouldReceive('get')->with('webpush.vapid_public_key')->andReturn(null);
        $component = new GeneralSettingsPage();
        $component->mount($mock);

        $this->assertSame('', $component->template_topic_name);
        $this->assertSame('', $component->group_id);
        $this->assertSame('', $component->vapidPublicKey);
    }

    public function test_save_persists_template_topic_name_and_group_id(): void
    {
        // A non-empty group_id triggers verify-before-save (token + getChat + admin).
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 7, 'username' => 'bot']], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => true, 'result' => ['id' => -100123, 'type' => 'supergroup']], 200),
            'https://api.telegram.org/*/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'administrator']], 200),
        ]);

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('telegram.template_topic_name')->andReturn('');
        $mock->shouldReceive('get')->with('telegram.group_id')->andReturn('');
        $mock->shouldReceive('get')->with('webpush.vapid_public_key')->andReturn('');
        $mock->shouldReceive('get')->with('telegram.token')->andReturn('bot123:valid');
        $mock->shouldReceive('set')->with('telegram.template_topic_name', 'Новое обращение')->once();
        $mock->shouldReceive('set')->with('telegram.group_id', '-100123')->once();

        $component = new GeneralSettingsPage();
        $component->mount($mock);
        $component->template_topic_name = 'Новое обращение';
        $component->group_id = '-100123';
        $component->save($mock);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_save_allows_empty_group_id(): void
    {
        // The Telegram supergroup is optional (admin-panel works without it):
        // an empty group_id must save successfully and is persisted as ''.
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn('');
        $mock->shouldReceive('set')->with('telegram.template_topic_name', Mockery::any())->once();
        $mock->shouldReceive('set')->with('telegram.group_id', '')->once();

        $component = new GeneralSettingsPage();
        $component->mount($mock);
        $component->template_topic_name = 'Обращение';
        $component->group_id = ''; // optional — admin-only, no group mirroring
        $component->save($mock);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_save_rejects_group_id_exceeding_50_chars(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn('');
        $mock->shouldNotReceive('set');

        $component = new GeneralSettingsPage();
        $component->mount($mock);
        $component->template_topic_name = 'Обращение';
        $component->group_id = str_repeat('1', 51);
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('group_id', $component->formErrors);
        $this->assertSame('Максимальная длина — 50 символов.', $component->formErrors['group_id']);
    }

    public function test_save_does_not_persist_when_template_exceeds_255(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn('');
        $mock->shouldNotReceive('set');

        $component = new GeneralSettingsPage();
        $component->mount($mock);
        $component->template_topic_name = str_repeat('a', 256);
        $component->group_id = '-100123'; // valid
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('template_topic_name', $component->formErrors);
    }

    public function test_save_push_subscription_stores_a_row_for_the_current_user(): void
    {
        $component = new GeneralSettingsPage();

        $component->savePushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-token'],
        ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://push.example.com/abc',
            'user_id' => auth()->id(),
        ]);
    }

    public function test_save_push_subscription_updates_the_existing_row_for_the_same_endpoint(): void
    {
        $component = new GeneralSettingsPage();
        $component->savePushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'old-key', 'auth' => 'old-token'],
        ]);

        $component->savePushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-token'],
        ]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['public_key' => 'new-key']);
    }

    public function test_save_push_subscription_ignores_incomplete_payloads(): void
    {
        $component = new GeneralSettingsPage();

        $component->savePushSubscription(['endpoint' => 'https://push.example.com/abc']);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
