<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\ChannelStatusService;
use App\Services\Settings\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Integrations overview page — lists Telegram, VK, MAX and Avito channel
 * cards with connection status and links to per-channel config pages.
 *
 * Access: authenticated users via route middleware + custom Livewire route.
 * Layout: custom dark-sidebar admin layout (layouts.admin-settings).
 */
#[Layout('layouts.admin-settings')]
class IntegrationsListPage extends Component
{
    /**
     * @var array<string, array{connected: bool, label: string}>
     */
    public array $channelStatuses = [];

    /** Whether Avito API credentials are stored (treated as "connected"). */
    public bool $avitoConnected = false;

    /**
     * Load channel statuses on mount.
     */
    public function mount(ChannelStatusService $channelStatus, SettingsService $settings): void
    {
        $this->channelStatuses = $channelStatus->all();

        // Avito is a built-in core module (same as Telegram/VK/Max). "Connected"
        // mirrors the other channels: the credentials required to call the API
        // are present.
        $this->avitoConnected = $settings->has('avito.client_id') && $settings->has('avito.client_secret');
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.integrations-list-page');
    }
}
