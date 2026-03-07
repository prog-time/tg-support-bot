<?php

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\Controllers\AiTelegramBotController;
use App\Modules\Telegram\Controllers\TelegramBotController;
use App\Modules\Telegram\Middleware\TelegramQuery;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'telegram',
], function () {
    Route::post('ai/bot', [AiTelegramBotController::class, 'bot_query'])->middleware(TelegramQuery::class);

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
