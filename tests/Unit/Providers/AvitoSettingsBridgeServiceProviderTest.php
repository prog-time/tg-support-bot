<?php

namespace Tests\Unit\Providers;

use App\Providers\AvitoSettingsBridgeServiceProvider;
use App\Services\Settings\SettingsService;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for AvitoSettingsBridgeServiceProvider.
 *
 * The provider pushes stored Avito API credentials into config('avito.*') at
 * boot, but only when the paid module is installed. In the test environment the
 * module is absent, so boot() must short-circuit before touching SettingsService.
 */
class AvitoSettingsBridgeServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_boot_is_noop_when_module_absent(): void
    {
        // Module not installed in tests → no settings reads, no config writes.
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldNotReceive('get');

        $provider = new AvitoSettingsBridgeServiceProvider($this->app);
        $provider->boot($settings);

        $this->assertNull(config('avito.client_id'));
    }
}
