<?php

use App\Middleware\VkQuery;

use App\Middleware\TelegramQuery;
use App\TelegramBot\TelegramMethods;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VkBotController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\ExternalTrafficController;

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

Route::group([
    'prefix' => 'external',
], function () {

    Route::group([
        'prefix' => 'messages',
    ], function () {
        Route::get('/{id_message}', [ExternalTrafficController::class, 'show'])->name('show');
        Route::get('/', [ExternalTrafficController::class, 'index'])->name('index');
        Route::post('/', [ExternalTrafficController::class, 'store'])->name('store');
        Route::put('/', [ExternalTrafficController::class, 'update'])->name('update');
        Route::delete('/', [ExternalTrafficController::class, 'destroy'])->name('destroy');
    });

    Route::post('bot', [TelegramBotController::class, 'bot_query'])->middleware(TelegramQuery::class);
});
