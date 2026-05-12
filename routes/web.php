<?php

use App\Http\Controllers\Poc\AppApiController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'poc.app')->name('poc.app');

Route::redirect('/app', '/');
Route::redirect('/login', '/')->name('login');

Route::prefix('poc')
    ->name('poc.')
    ->group(function () {
        Route::get('/documents/{subDocument}/preview', [AppApiController::class, 'previewSubDocument'])
            ->whereNumber('subDocument')
            ->name('documents.preview');

        Route::prefix('api')
            ->name('api.')
            ->middleware('throttle:60,1')
            ->group(function () {
                Route::get('/state', [AppApiController::class, 'state'])->name('state');
                Route::post('/communications', [AppApiController::class, 'generateCommunication'])
                    ->middleware('throttle:20,1')
                    ->name('communications.generate');
                Route::post('/documents/ocr', [AppApiController::class, 'runDocumentOcr'])
                    ->middleware('throttle:20,1')
                    ->name('documents.ocr');
                Route::get('/documents/{originalDocument}/stream', [AppApiController::class, 'streamDocumentProcessing'])
                    ->whereNumber('originalDocument')
                    ->name('documents.stream');
                Route::delete('/documents/{subDocument}', [AppApiController::class, 'deleteSubDocument'])
                    ->whereNumber('subDocument')
                    ->name('documents.delete');
            });
    });
