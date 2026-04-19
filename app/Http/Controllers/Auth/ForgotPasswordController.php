<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{

    public function showForm()
    {
        return view('auth.forgot-password');
    }


    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Không tìm thấy tài khoản với email này.'],
            ]);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        session([
            'password_reset' => [
                'email' => $request->email,
                'name' => $user->name,
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
                'verified' => false,
            ],
        ]);

        Mail::to($request->email)->send(
            new ResetPasswordCodeMail($otp, $user->name)
        );

        return redirect()->route('password.verify.otp');
    }

    public function showVerifyOtp()
    {
        if (!session('password_reset')) {
            return redirect()->route('password.request')
                ->with('error', 'Vui lòng nhập email trước.');
        }

        return view('auth.forgot-password-otp', [
            'email' => session('password_reset.email'),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $reset = session('password_reset');

        if (!$reset) {
            throw ValidationException::withMessages([
                'otp' => ['Phiên đã hết hạn. Vui lòng thử lại.'],
            ]);
        }

        if (now()->greaterThan($reset['otp_expires_at'])) {
            throw ValidationException::withMessages([
                'otp' => ['Mã xác thực đã hết hạn. Vui lòng gửi lại mã mới.'],
            ]);
        }

        if ((string)$request->otp !== (string)$reset['otp']) {
            throw ValidationException::withMessages([
                'otp' => ['Mã xác thực không đúng. Vui lòng thử lại.'],
            ]);
        }

        $reset['verified'] = true;
        session(['password_reset' => $reset]);

        return redirect()->route('password.reset.form');
    }

    public function resendOtp()
    {
        $reset = session('password_reset');

        if (!$reset) {
            return redirect()->route('password.request')
                ->with('error', 'Vui lòng nhập email trước.');
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $reset['otp'] = $otp;
        $reset['otp_expires_at'] = now()->addMinutes(5);
        session(['password_reset' => $reset]);

        Mail::to($reset['email'])->send(
            new ResetPasswordCodeMail($otp, $reset['name'])
        );

        return back()->with('success', 'Mã xác thực mới đã được gửi!');
    }
}
