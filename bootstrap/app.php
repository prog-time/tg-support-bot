<?php

use App\Exceptions\TgBotException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Logging\LokiLogger;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        /**
         * Sending log in Loki
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            (new LokiLogger())->sendBasicLog($e);
            return response('ok', 200);
        });

        /**
         * For this code to work, you need to install the prog-time/tg-logger module.
         * https://github.com/prog-time/tg-logger
         */
        if (!empty(env('TG_LOGGER_TOKEN'))) {
            $exceptions->render(function (Throwable $e, Request $request) {
                (new TgBotException)->render($request, $e);
                return response('ok', 200);
            });
        }

    })->create();
