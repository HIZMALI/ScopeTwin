<?php

use App\Http\Controllers\PublicDemoController;
use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

// Stateless: no session, auth, CSRF cookie, cache throttle or GET writes.
Route::get('/', [PublicDemoController::class, 'landing'])->name('home');
Route::get('/demo', [PublicDemoController::class, 'show'])->name('demo');
Route::get('/demo/replay', [PublicDemoController::class, 'show'])->name('demo.replay');
Route::get('/ready', ReadinessController::class)->name('ready');
