<?php

use App\Http\Controllers\TelegramBotController;

use App\Http\Controllers\VkBotController;
use App\Middleware\TelegramQuery;
use App\Middleware\VkQuery;
use App\TelegramBot\TelegramMethods;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'telegram',
], function () {
    Route::post('bot', [TelegramBotController::class, 'bot_query'])->middleware(TelegramQuery::class);

    Route::get('set_webhook', function () {
        $queryParams = [
            'url' => env('APP_URL') . '/api/telegram/bot',
            'max_connections' => 40,
            'drop_pending_updates' => true,
            'secret_token' => env('TELEGRAM_SECRET_KEY'),
        ];
        $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams);

        return response()->json($result->rawData);
    });
});

Route::post('vk/bot', [VkBotController::class, 'bot_query'])->middleware(VkQuery::class);
