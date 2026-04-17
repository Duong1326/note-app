<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthControler;
use App\Http\Controllers\LabelController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');
    Route::post('/register', [AuthControler::class, 'register']);

    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
    Route::post('/login', [AuthControler::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthControler::class, 'logout'])->name('logout');

    Route::get('/notes', function () {
        return view('notes');
    })->name('notes');

    // Quản lý nhãn (Labels)
    Route::resource('labels', LabelController::class)->only(['index', 'store', 'update', 'destroy']);
});
