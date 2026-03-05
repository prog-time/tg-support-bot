<?php

use App\Http\Controllers\FilesController;
use Illuminate\Support\Facades\Route;

// Telegram routes are registered by App\Modules\Telegram\TelegramServiceProvider
// VK routes are registered by App\Modules\Vk\VkServiceProvider
// External routes are registered by App\Modules\External\ExternalServiceProvider

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
