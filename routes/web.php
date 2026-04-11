<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthControler;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/notes', function () {
    return view('notes');
});
Route::post('/register', [AuthControler::class, 'register']);
