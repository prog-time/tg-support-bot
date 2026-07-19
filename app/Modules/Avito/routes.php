<?php

use App\Modules\Avito\Controllers\AvitoBotController;
use App\Modules\Avito\Middleware\AvitoQuery;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'avito',
], function () {
    // The {secret} path segment authenticates the webhook (see AvitoQuery).
    // Optional so the endpoint still resolves when no secret is configured.
    Route::post('bot/{secret?}', [AvitoBotController::class, 'bot_query'])->middleware(AvitoQuery::class);
});
