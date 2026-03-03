<?php

namespace App\Modules\Telegram;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap Telegram module routes.
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
