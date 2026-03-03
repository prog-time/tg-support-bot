<?php

use App\Http\Controllers\ExternalTrafficController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\VkBotController;
use App\Middleware\ApiQuery;
use App\Middleware\VkQuery;
use Illuminate\Support\Facades\Route;

// Telegram routes are registered by App\Modules\Telegram\TelegramServiceProvider

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
        'prefix' => '{external_id}',
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

        Route::group([
            'prefix' => 'files',
        ], function () {
            Route::post('/', [ExternalTrafficController::class, 'sendFile'])->name('file_send');
        });
    });
});

Route::group([
    'prefix' => 'files',
], function () {
    Route::get('{file_id}', [FilesController::class, 'getFileStream'])
        ->where('file_id', '[A-Za-z0-9\-_]+')
        ->name('stream_file');

    Route::post('{file_id}', [FilesController::class, 'getFileDownload'])
        ->where('file_id', '[A-Za-z0-9\-_]+')
        ->name('download_file');
});
