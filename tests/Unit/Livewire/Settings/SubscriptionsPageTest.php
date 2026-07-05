<?php

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\SubscriptionsPage;
use App\Models\User;
use App\Services\Licensing\LicenseClient;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for the SubscriptionsPage Livewire component.
 *
 * The «Подписки» screen checks one shared license key against the License
 * Server, lists the modules it grants, and on success persists the key and
 * mirrors it into each installed module's own license key. Admin-only.
 */
class SubscriptionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        config(['license.module_keys' => ['avito' => 'avito.license_key']]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** @return array{success: bool, message: string, products: array<int, array<string, mixed>>} */
    private function okResult(): array
    {
        return [
            'success' => true,
            'message' => 'ok',
            'products' => [
                ['product' => 'avito', 'name' => 'Avito', 'valid_until' => '2026-12-31', 'status' => 'active'],
            ],
        ];
    }

    public function test_mount_auto_lists_modules_from_stored_key(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('has')->with('license.key')->andReturn(true);
        $settings->shouldReceive('get')->with('license.key')->andReturn('STORED');

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldReceive('products')->with('STORED')->once()->andReturn($this->okResult());

        $component = new SubscriptionsPage();
        $component->mount($settings, $client);

        $this->assertTrue($component->hasKey);
        $this->assertTrue($component->checked);
        $this->assertCount(1, $component->products);
    }

    public function test_mount_does_not_fetch_when_no_key_stored(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('has')->with('license.key')->andReturn(false);

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldNotReceive('products');

        $component = new SubscriptionsPage();
        $component->mount($settings, $client);

        $this->assertFalse($component->hasKey);
        $this->assertFalse($component->checked);
    }

    public function test_check_lists_products_and_persists_with_mirror(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('set')->with('license.key', 'KEY')->once();
        $settings->shouldReceive('set')->with('avito.license_key', 'KEY')->once();
        $settings->shouldReceive('has')->with('license.key')->andReturn(true);

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldReceive('products')->with('KEY')->once()->andReturn($this->okResult());

        $component = new SubscriptionsPage();
        $component->license_key = 'KEY';
        $component->check($settings, $client);

        $this->assertTrue($component->checked);
        $this->assertCount(1, $component->products);
        $this->assertSame('Avito', $component->products[0]['name']);
        $this->assertSame('', $component->license_key); // cleared after check
        $this->assertTrue($component->hasKey);
        $this->assertNull($component->errorMessage);
    }

    public function test_check_uses_stored_key_when_field_blank(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->with('license.key')->andReturn('STORED');
        // Blank field → key unchanged → nothing persisted.
        $settings->shouldNotReceive('set');
        $settings->shouldReceive('has')->with('license.key')->andReturn(true);

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldReceive('products')->with('STORED')->once()->andReturn($this->okResult());

        $component = new SubscriptionsPage();
        $component->license_key = '';
        $component->check($settings, $client);

        $this->assertTrue($component->checked);
        $this->assertCount(1, $component->products);
    }

    public function test_check_errors_when_no_key_available(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->with('license.key')->andReturn('');
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldNotReceive('products');

        $component = new SubscriptionsPage();
        $component->license_key = '';
        $component->check($settings, $client);

        $this->assertFalse($component->checked);
        $this->assertNotNull($component->errorMessage);
    }

    public function test_check_shows_error_and_persists_nothing_on_failure(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldReceive('products')->with('BAD')->once()->andReturn([
            'success' => false,
            'message' => 'Сервер лицензий вернул ошибку.',
            'products' => [],
        ]);

        $component = new SubscriptionsPage();
        $component->license_key = 'BAD';
        $component->check($settings, $client);

        $this->assertFalse($component->checked);
        $this->assertSame('Сервер лицензий вернул ошибку.', $component->errorMessage);
    }

    public function test_check_is_ignored_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&LicenseClient $client */
        $client = Mockery::mock(LicenseClient::class);
        $client->shouldNotReceive('products');

        $component = new SubscriptionsPage();
        $component->license_key = 'KEY';
        $component->check($settings, $client);

        $this->assertFalse($component->checked);
    }
}
