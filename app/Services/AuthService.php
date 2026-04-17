<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthService
{

    public function initiateRegistration(array $data): void
    {
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email đã được sử dụng.'],
            ]);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        session([
            'registration' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
            ],
        ]);

        // Gửi email OTP
        Mail::to($data['email'])->send(
            new VerificationCodeMail($otp, $data['name'])
        );
    }


    public function verifyOtpAndCreateUser(string $inputOtp): User
    {
        $registration = session('registration');

        if (!$registration) {
            throw ValidationException::withMessages([
                'otp' => ['Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.'],
            ]);
        }

        if (now()->greaterThan($registration['otp_expires_at'])) {
            throw ValidationException::withMessages([
                'otp' => ['Mã xác thực đã hết hạn. Vui lòng gửi lại mã mới.'],
            ]);
        }

        if ($inputOtp !== $registration['otp']) {
            throw ValidationException::withMessages([
                'otp' => ['Mã xác thực không đúng. Vui lòng thử lại.'],
            ]);
        }

        try {
            $user = User::create([
                'name' => $registration['name'],
                'email' => $registration['email'],
                'password' => $registration['password'],
                'email_verified_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'email' => ['Email đã được sử dụng.'],
                ]);
            }
            throw $e;
        }

        session()->forget('registration');

        return $user;
    }


    public function resendOtp(): void
    {
        $registration = session('registration');

        if (!$registration) {
            throw ValidationException::withMessages([
                'otp' => ['Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.'],
            ]);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $registration['otp'] = $otp;
        $registration['otp_expires_at'] = now()->addMinutes(5);
        session(['registration' => $registration]);

        Mail::to($registration['email'])->send(
            new VerificationCodeMail($otp, $registration['name'])
        );
    }

    public function login(array $credentials, bool $remember = false): User
    {
        if (
            !Auth::attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ], $remember)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();

        request()->session()->regenerate();

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
