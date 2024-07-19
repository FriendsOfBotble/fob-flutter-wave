<?php

use FriendsOfBotble\FlutterWave\Http\Controllers\FlutterWaveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['core', 'web'])->prefix('payment/flutter-wave')->name('payment.flutter-wave.')->group(function () {
    Route::get('callback', [FlutterWaveController::class, 'callback'])->name('callback');
});

Route::middleware(['core'])->prefix('payment/flutter-wave')->name('payment.flutter-wave.')->group(function () {
    Route::post('webhook', [FlutterWaveController::class, 'webhook'])->name('webhook');
});
