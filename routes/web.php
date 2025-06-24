<?php

use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'docs',
    'as' => 'docs.',
], function () {
    Route::get('/swagger-v1-json', [SwaggerController::class, 'showSwagger']);
    Route::get('/swagger-v1-ui', [SwaggerController::class, 'swaggerUi']);
});
