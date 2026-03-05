<?php

namespace App\Modules\Vk;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VkServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap VK module routes.
     *
     * @return void
     */
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/routes.php');
    }
}
