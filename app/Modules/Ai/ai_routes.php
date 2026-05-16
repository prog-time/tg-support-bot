<?php

use App\Modules\Ai\Controllers\AiBotController;
use App\Modules\Ai\Middleware\AiBotQuery;
use Illuminate\Support\Facades\Route;

Route::post('/api/ai-bot/webhook', [AiBotController::class, 'handle'])
    ->middleware(AiBotQuery::class)
    ->name('ai-bot.webhook');
