<?php

use App\Modules\Max\Controllers\MaxBotController;
use App\Modules\Max\Middleware\MaxQuery;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'max',
], function () {
    Route::post('bot', [MaxBotController::class, 'bot_query'])->middleware(MaxQuery::class);
});
