<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Contracts\AiProviderInterface;
use App\Modules\Ai\Services\AiSystemPromptLoader;
use App\Modules\Ai\Services\DeepSeekProvider;
use App\Modules\Ai\Services\GigaChatProvider;
use App\Modules\Ai\Services\OpenAiProvider;
use App\Services\Settings\SettingsService;
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
        $this->app->singleton(AiSystemPromptLoader::class);

        $this->app->bind(AiProviderInterface::class, function () {
            $provider = (string) ($this->app->make(SettingsService::class)->get('ai.default_provider') ?? 'openai');

            return match ($provider) {
                'openai' => $this->app->make(OpenAiProvider::class),
                'deepseek' => $this->app->make(DeepSeekProvider::class),
                'gigachat' => $this->app->make(GigaChatProvider::class),
                default => $this->app->make(OpenAiProvider::class),
            };
        });
    }
}
