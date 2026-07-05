<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Modules\Admin\Services\ChannelStatusService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Integrations overview page — lists Telegram, VK, MAX channel cards
 * with connection status and links to per-channel config pages.
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

    /** Whether the paid Avito module is installed (its settings route exists). */
    public bool $avitoInstalled = false;

    /** Whether an Avito license key is stored (treated as "connected"). */
    public bool $avitoConnected = false;

    /**
     * Load channel statuses on mount.
     */
    public function mount(ChannelStatusService $channelStatus, SettingsService $settings): void
    {
        $this->channelStatuses = $channelStatus->all();

        // The Avito card only appears when the pluggable module is installed
        // (it registers the admin.settings.avito route). "Connected" mirrors the
        // other channels: credentials present — here, a stored license key.
        $this->avitoInstalled = Route::has('admin.settings.avito');

        if ($this->avitoInstalled) {
            $this->avitoConnected = $settings->has('avito.license_key');
        }
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
