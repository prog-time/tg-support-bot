<?php

use App\Modules\Api\Controllers\PreviewController;
use App\Modules\Api\Controllers\SimplePage;
use App\Modules\Api\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::prefix('docs')->group(function () {
    Route::get('/swagger-v1-json', [SwaggerController::class, 'showSwagger']);
    Route::get('/swagger-v1-ui', [SwaggerController::class, 'swaggerUi']);
});

Route::get('/preview/chat', [PreviewController::class, 'chat']);
Route::get('/', [SimplePage::class, 'index']);
Route::get('/live_chat_promo', [SimplePage::class, 'liveChatPromo']);
