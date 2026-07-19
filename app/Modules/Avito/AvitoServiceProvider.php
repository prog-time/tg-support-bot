<?php

namespace App\Modules\Avito;

use App\Modules\Avito\Console\SetWebhookCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AvitoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap Avito module routes and commands.
     *
     * @return void
     */
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SetWebhookCommand::class,
            ]);
        }
    }
}
