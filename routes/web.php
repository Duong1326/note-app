<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthControler;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\NoteControler;

Route::get('/', function () {
    return redirect()->route('login');
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
        $notesQuery = $user->notes();

        if (request()->has('q') && !empty(request()->q)) {
            $q = request()->q;
            $notesQuery->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            });
        }

        return view('dashboard', [
            'recentNotes' => $notesQuery->clone()->with('labels')->defaultOrder()->take(request()->has('q') ? 50 : 6)->get(),
            'pinnedNotes' => $notesQuery->clone()->where('is_pinned', true)->defaultOrder()->get(),
            'totalNotes' => $notesQuery->clone()->count(),
            'weeklyNotes' => $notesQuery->clone()->where('created_at', '>=', now()->subWeek())->count(),
            'labels' => $user->labels()->orderBy('name')->get(),
            'searchQuery' => request()->q,
        ]);
    })->name('dashboard');

    Route::post('/logout', [AuthControler::class, 'logout'])->name('logout');

    Route::resource('notes', NoteControler::class);
    Route::post('/notes/{note}/pin', [NoteControler::class, 'pin'])->name('notes.pin');
    Route::post('/notes/{note}/unpin', [NoteControler::class, 'unpin'])->name('notes.unpin');

    Route::resource('labels', LabelController::class)->only(['index', 'store', 'update', 'destroy']);

    // Attachment (Cloudinary image upload)
    Route::post('/notes/{note}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/notes/{note}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Profile
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

    Route::put('/profile', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bio'  => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->update($request->only('name', 'bio'));

        return back()->with('success', 'Cập nhật thông tin thành công!');
    })->name('profile.update');
});
