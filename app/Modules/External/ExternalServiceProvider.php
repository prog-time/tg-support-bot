<?php

namespace App\Modules\External;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ExternalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap External module routes.
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
