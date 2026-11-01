<?php

namespace Tests\Unit\Modules\Ai;

use App\Modules\Ai\Contracts\AiProviderInterface;
use App\Modules\Ai\Services\AiSystemPromptLoader;
use App\Modules\Ai\Services\DeepSeekProvider;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_loader_is_a_singleton(): void
    {
        $this->assertSame(app(AiSystemPromptLoader::class), app(AiSystemPromptLoader::class));
    }

    public function test_provider_binding_follows_the_setting(): void
    {
        app(SettingsService::class)->set('ai.default_provider', 'deepseek');

        $this->assertInstanceOf(DeepSeekProvider::class, app(AiProviderInterface::class));
    }

    public function test_module_registers_no_webhook_routes(): void
    {
        $this->assertFalse(collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route) => str_starts_with($route->uri(), 'api/ai-bot')
        ));
    }
}
