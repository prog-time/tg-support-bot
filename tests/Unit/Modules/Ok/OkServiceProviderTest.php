<?php

namespace Tests\Unit\Modules\Ok;

use App\Modules\Ok\Controllers\OkBotController;
use App\Modules\Ok\Middleware\OkQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OkServiceProviderTest extends TestCase
{
    public function test_registers_the_protected_webhook_route(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === 'api/ok/bot/{secret}');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame(OkBotController::class . '@bot_query', ltrim($route->getActionName(), '\\'));
        $this->assertContains(OkQuery::class, $route->gatherMiddleware());
    }

    public function test_registers_the_console_command(): void
    {
        $this->assertArrayHasKey('ok:set-webhook', Artisan::all());
    }
}
