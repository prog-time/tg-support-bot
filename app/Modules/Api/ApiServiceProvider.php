<?php

namespace App\Modules\Api;

use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать роуты Api-модуля.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/api_routes.php');
        $this->loadRoutesFrom(__DIR__ . '/web_routes.php');
    }
}
