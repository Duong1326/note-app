<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthControler;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\LabelController;

Route::get('/', function () {
    return view('welcome');
})->name('home');


//guest
Route::middleware('guest')->group(function () {
    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');
    Route::post('/register', [AuthControler::class, 'register']);

    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');
    Route::post('/login', [AuthControler::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->name('password.email');

    Route::get('/forgot-password/verify', [ForgotPasswordController::class, 'showVerifyOtp'])->name('password.verify.otp');
    Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verifyOtp'])->name('password.verify.otp.submit');
    Route::post('/forgot-password/resend', [ForgotPasswordController::class, 'resendOtp'])
        ->middleware('throttle:3,1')->name('password.resend.otp');

    Route::get('/reset-password', [ResetPasswordController::class, 'showForm'])->name('password.reset.form');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::get('/verify-otp', [AuthControler::class, 'showVerifyOtp'])->name('verify.otp');
Route::post('/verify-otp', [AuthControler::class, 'verifyOtp'])->name('verify.otp.submit');
Route::post('/resend-otp', [AuthControler::class, 'resendOtp'])
    ->middleware('throttle:3,1')->name('verify.otp.resend');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $notes = $user->notes();

        return view('dashboard', [
            'recentNotes' => $notes->clone()->with('labels')->latest()->take(6)->get(),
            'pinnedNotes' => $notes->clone()->where('is_pinned', true)->latest()->get(),
            'totalNotes' => $notes->clone()->count(),
            'weeklyNotes' => $notes->clone()->where('created_at', '>=', now()->subWeek())->count(),
            'labels' => $user->labels()->orderBy('name')->get(),
        ]);
    })->name('dashboard');

    Route::post('/logout', [AuthControler::class, 'logout'])->name('logout');

    Route::get('/notes', function () {
        return view('notes');
    })->name('notes.index');

    Route::resource('labels', LabelController::class)->only(['index', 'store', 'update', 'destroy']);
});
