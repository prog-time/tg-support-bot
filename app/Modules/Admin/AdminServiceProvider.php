<?php

namespace App\Modules\Admin;

use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать Admin-модуль.
     * Filament-роуты регистрируются через AdminPanelProvider.
     */
    public function boot(): void
    {
        // Filament роуты регистрируются через AdminPanelProvider
    }
}
