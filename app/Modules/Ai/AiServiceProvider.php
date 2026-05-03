<?php

namespace App\Modules\Ai;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap AI module routes.
     *
     * @return void
     */
    public function boot(): void
    {
        Route::middleware('api')
            ->group(__DIR__ . '/ai_routes.php');
    }
}
