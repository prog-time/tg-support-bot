<?php

namespace App\Modules\Ok;

use App\Modules\Ok\Console\SetWebhookCommand;
use App\Modules\Ok\Controllers\OkBotController;
use App\Modules\Ok\Middleware\OkQuery;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OkServiceProvider extends ServiceProvider
{
    /**
     * Register the webhook route and the Odnoklassniki console command.
     *
     * @return void
     */
    public function boot(): void
    {
        Route::middleware(['api', OkQuery::class])
            ->post('api/ok/bot/{secret}', [OkBotController::class, 'bot_query']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SetWebhookCommand::class,
            ]);
        }
    }
}
