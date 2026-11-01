<?php

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\IntegrationsListPage;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for the IntegrationsListPage Livewire component.
 *
 * Exercises mount() in isolation with a mocked ChannelStatusService —
 * no DB or Livewire rendering required.
 */
class IntegrationsListPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_mount_loads_channel_statuses_from_service(): void
    {
        $statuses = [
            'telegram' => ['connected' => true, 'label' => 'Подключено'],
            'vk' => ['connected' => false, 'label' => 'Не подключён'],
            'max' => ['connected' => false, 'label' => 'Не подключён'],
        ];

        /** @var \Mockery\MockInterface&ChannelStatusService $mock */
        $mock = Mockery::mock(ChannelStatusService::class);
        $mock->shouldReceive('all')->with()->once()->andReturn($statuses);

        $component = new IntegrationsListPage();
        $component->mount($mock, app(SettingsService::class));

        $this->assertSame($statuses, $component->channelStatuses);
    }

    public function test_mount_handles_empty_statuses(): void
    {
        /** @var \Mockery\MockInterface&ChannelStatusService $mock */
        $mock = Mockery::mock(ChannelStatusService::class);
        $mock->shouldReceive('all')->with()->once()->andReturn([]);

        $component = new IntegrationsListPage();
        $component->mount($mock, app(SettingsService::class));

        $this->assertSame([], $component->channelStatuses);
    }
}
