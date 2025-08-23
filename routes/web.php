<?php

use App\Http\Controllers\SimplePage;
use App\Http\Controllers\SwaggerController;
use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'docs',
    'as' => 'docs.',
], function () {
    Route::get('/swagger-v1-json', [SwaggerController::class, 'showSwagger']);
    Route::get('/swagger-v1-ui', [SwaggerController::class, 'swaggerUi']);
});

Route::get('/preview/chat', [PreviewController::class, 'chat']);

Route::get('/', [SimplePage::class, 'index']);
