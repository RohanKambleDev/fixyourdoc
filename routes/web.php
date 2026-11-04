<?php

use App\Http\Controllers\FormatJobDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/format', 'format')->name('format.create');
    Route::view('/format/history', 'format-history')->name('format.history');
    Route::get('/format/jobs/{formatJob}/download/{type}', FormatJobDownloadController::class)
        ->whereIn('type', ['original', 'formatted'])
        ->name('format.download');
});
