<?php

namespace App\Providers;

use App\Contracts\ManagerInterfaceContract;
use App\Modules\Admin\Services\AdminPanelInterface;
use App\Modules\Telegram\Services\TelegramGroupInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ManagerInterfaceContract::class,
            config('app.manager_interface') === 'admin_panel'
                ? AdminPanelInterface::class
                : TelegramGroupInterface::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
