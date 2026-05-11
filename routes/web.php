<?php

use App\Http\Controllers\Auth\UserLoginController;
use App\Http\Controllers\Poc\AppApiController;
use App\Http\Controllers\Poc\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('poc.app')
        : redirect()->route('login');
})->name('home');

Route::get('/app', function () {
    return view('poc.app');
})->middleware('auth')->name('poc.app');

Route::get('/login', [UserLoginController::class, 'create'])->name('login');
Route::post('/login', [UserLoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('user.login.store');

Route::middleware(['auth'])
    ->prefix('poc')
    ->name('poc.')
    ->group(function () {
        Route::post('/logout', SessionController::class)
            ->middleware('throttle:10,1')
            ->name('logout');

        Route::prefix('api')
            ->name('api.')
            ->middleware('throttle:60,1')
            ->group(function () {
                Route::get('/session', [AppApiController::class, 'session'])->name('session');
                Route::get('/state', [AppApiController::class, 'state'])->name('state');
                Route::post('/communications', [AppApiController::class, 'generateCommunication'])
                    ->middleware('throttle:20,1')
                    ->name('communications.generate');
                Route::post('/documents/ocr', [AppApiController::class, 'runDocumentOcr'])
                    ->middleware('throttle:20,1')
                    ->name('documents.ocr');
            });
    });
