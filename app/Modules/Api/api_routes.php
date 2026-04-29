<?php

use App\Modules\Api\Controllers\FilesController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/files')->group(function () {
    Route::get('{file_id}', [FilesController::class, 'getFileStream'])
        ->where('file_id', '[A-Za-z0-9\-_]+')
        ->name('stream_file');

    Route::post('{file_id}', [FilesController::class, 'getFileDownload'])
        ->where('file_id', '[A-Za-z0-9\-_]+')
        ->name('download_file');
});
