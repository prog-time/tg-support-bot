<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            return response()->json([
                'message' => 'Route not found.',
            ], 404);
        });

        /**
         * Sending log in Loki
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface || $e instanceof RouteNotFoundException) {
                return null;
            }

            Log::channel('loki')->error('File: ' . $e->getFile() . '; Line: ' . $e->getLine() . '; Error: ' . $e->getMessage());

            if (env('APP_DEBUG') === false) {
                return response('ok', 200);
            }
        });
    })->create();
