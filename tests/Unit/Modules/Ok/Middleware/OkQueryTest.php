<?php

namespace Tests\Unit\Modules\Ok\Middleware;

use App\Modules\Ok\Middleware\OkQuery;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OkQueryTest extends TestCase
{
    use RefreshDatabase;

    private function handle(string $provided): Response
    {
        $request = Request::create('/api/ok/bot/' . $provided, 'POST');
        $route = new Route('POST', 'api/ok/bot/{secret}', []);
        $route->bind($request);
        $route->setParameter('secret', $provided);
        $request->setRouteResolver(fn () => $route);

        return (new OkQuery())->handle($request, fn () => response('passed', 200));
    }

    public function test_right_secret_passes(): void
    {
        app(SettingsService::class)->set('ok.webhook_secret', 'right');

        $this->assertSame(200, $this->handle('right')->getStatusCode());
    }

    public function test_wrong_secret_is_forbidden(): void
    {
        app(SettingsService::class)->set('ok.webhook_secret', 'right');

        $this->assertSame(403, $this->handle('wrong')->getStatusCode());
    }

    public function test_empty_configured_secret_is_forbidden(): void
    {
        $this->assertSame(403, $this->handle('')->getStatusCode());
    }

    public function test_settings_failure_answers_502_for_redelivery(): void
    {
        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->andThrow(new \RuntimeException('down'));
        $this->app->instance(SettingsService::class, $settings);

        $this->assertSame(502, $this->handle('right')->getStatusCode());
    }
}
