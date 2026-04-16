<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthControler;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/notes', function () {
    return view('notes');
})->name('notes');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');
Route::post('/register', [AuthControler::class, 'register']);

Route::get('/login', function () {
    return view('auth.login');
})->name('login');
Route::post('/login', [AuthControler::class, 'login']);

Route::post('/logout', [AuthControler::class, 'logout'])->name('logout');