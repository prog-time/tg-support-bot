<?php

namespace App\Modules\Max;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MaxServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap Max module routes.
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
