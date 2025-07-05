<?php

use App\Http\Controllers\ExternalTrafficController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\VkBotController;
use App\Middleware\ApiQuery;
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
            'url' => config('app.url') . '/api/telegram/bot',
            'max_connections' => 40,
            'drop_pending_updates' => true,
            'secret_token' => config('traffic_source.settings.telegram.secret_key'),
        ];
        $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams);

        return response()->json($result->rawData);
    });
});

Route::group([
    'prefix' => 'vk',
], function () {
    Route::post('bot', [VkBotController::class, 'bot_query'])->middleware(VkQuery::class);
});

Route::group([
    'prefix' => 'external',
    'middleware' => ApiQuery::class,
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
});

Route::get('files/{file_id}', [FilesController::class, 'getFile'])
    ->where('file_id', '[A-Za-z0-9\-_]+')
    ->name('download_file');

Route::group([
    'prefix' => 'test',
], function () {
    Route::post('webhook', [TestController::class, 'webhook'])->name('test_webhook');
});
