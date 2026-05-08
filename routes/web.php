<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\NoteLockController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteShareController;
use App\Http\Controllers\ProfileController;

Route::get('/', [AuthController::class, 'redirectHome'])->name('home');

// Public health check endpoint
Route::get('/health', function () {
    $status = 'ok';
    $checks = [];

    // Check database connectivity
    try {
        \DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error';
        $status = 'degraded';
    }

    return response()->json([
        'status'    => $status,
        'timestamp' => now()->toIso8601String(),
        'app'       => config('app.name'),
        'env'       => config('app.env'),
        'checks'    => $checks,
    ], $status === 'ok' ? 200 : 503);
})->name('health');

//guest
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->name('password.email');

    Route::get('/forgot-password/verify', [ForgotPasswordController::class, 'showVerifyOtp'])->name('password.verify.otp');
    Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verifyOtp'])->name('password.verify.otp.submit');
    Route::post('/forgot-password/resend', [ForgotPasswordController::class, 'resendOtp'])
        ->middleware('throttle:3,1')->name('password.resend.otp');

    Route::get('/reset-password', [ResetPasswordController::class, 'showForm'])->name('password.reset.form');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::get('/verify-otp', [AuthController::class, 'showVerifyOtp'])->name('verify.otp');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('verify.otp.submit');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])
    ->middleware('throttle:3,1')->name('verify.otp.resend');

Route::middleware(['auth', \App\Http\Middleware\PreventBackHistory::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/search', [DashboardController::class, 'search'])->name('dashboard.search');
    Route::get('/dashboard/filter-label', [DashboardController::class, 'filterByLabel'])->name('dashboard.filter.label');
    Route::get('/dashboard/load-more', [DashboardController::class, 'loadMore'])->name('dashboard.load.more');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::resource('notes', NoteController::class)->only(['store']);

    // Full-page note editor: create new note
    Route::get('/notes/create', [NoteController::class, 'create'])->name('notes.create');

    // Full-page note editor view (read-only route, no note.token needed)
    Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');

    // Mutation routes that require a valid unlock token for locked notes
    Route::middleware('note.token')->group(function () {
        Route::put('/notes/{note}',    [NoteController::class, 'update'])->name('notes.update');
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
        Route::post('/notes/{note}/pin',   [NoteController::class, 'pin'])->name('notes.pin');
        Route::post('/notes/{note}/unpin', [NoteController::class, 'unpin'])->name('notes.unpin');

        // Attachment (Cloudinary image upload)
        Route::post('/notes/{note}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/notes/{note}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    });

    // Note lock management
    Route::post('/notes/{note}/lock/verify',  [NoteLockController::class, 'verify'])->name('notes.lock.verify');
    Route::post('/notes/{note}/lock/enable',  [NoteLockController::class, 'enable'])->name('notes.lock.enable');
    Route::put('/notes/{note}/lock/password', [NoteLockController::class, 'changePassword'])->name('notes.lock.change');
    Route::delete('/notes/{note}/lock',       [NoteLockController::class, 'disable'])->name('notes.lock.disable');

    Route::resource('labels', LabelController::class)->only(['index', 'store', 'update', 'destroy']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

    // Note Sharing
    Route::get('/shared-notes', [NoteShareController::class, 'sharedWithMe'])->name('notes.shared');
    Route::get('/shared-notes/cards', [NoteShareController::class, 'sharedWithMeCards'])->name('notes.shared.cards');
    Route::get('/notes/{note}/shares', [NoteShareController::class, 'index'])->name('notes.shares.index');
    Route::get('/notes/{note}/shared-view', [NoteShareController::class, 'sharedView'])->name('notes.shared.view');
    Route::post('/notes/{note}/shares', [NoteShareController::class, 'store'])->name('notes.shares.store');
    Route::put('/notes/{note}/shares/{share}', [NoteShareController::class, 'update'])->name('notes.shares.update');
    Route::delete('/notes/{note}/shares/{share}', [NoteShareController::class, 'destroy'])->name('notes.shares.destroy');
});
