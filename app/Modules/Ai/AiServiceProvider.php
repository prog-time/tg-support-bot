<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Contracts\AiProviderInterface;
use App\Modules\Ai\Services\DeepSeekProvider;
use App\Modules\Ai\Services\GigaChatProvider;
use App\Modules\Ai\Services\OpenAiProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register AI provider binding based on config.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            return match (config('ai.default_provider')) {
                'openai' => $this->app->make(OpenAiProvider::class),
                'deepseek' => $this->app->make(DeepSeekProvider::class),
                'gigachat' => $this->app->make(GigaChatProvider::class),
                default => $this->app->make(OpenAiProvider::class),
            };
        });
    }

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
