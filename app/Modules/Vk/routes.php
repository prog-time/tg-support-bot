<?php

use App\Modules\Vk\Controllers\VkBotController;
use App\Modules\Vk\Middleware\VkQuery;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'vk',
], function () {
    Route::post('bot', [VkBotController::class, 'bot_query'])->middleware(VkQuery::class);
});
