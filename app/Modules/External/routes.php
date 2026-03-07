<?php

use App\Modules\External\Controllers\ExternalTrafficController;
use App\Modules\External\Middleware\ApiQuery;
use Illuminate\Support\Facades\Route;

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
