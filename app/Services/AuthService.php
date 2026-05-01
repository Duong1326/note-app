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
                'otp_expires_at' => now()->addMinutes(5)->toISOString(),
            ],
        ]);

        try {
            Mail::to($data['email'])->send(
                new VerificationCodeMail($otp, $data['name'])
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi gửi mail đăng ký: ' . $e->getMessage(), [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            $detail = config('app.debug') ? ' [DEBUG: ' . get_class($e) . ': ' . $e->getMessage() . ']' : '';
            throw ValidationException::withMessages([
                'email' => ['Hệ thống đang gặp sự cố khi gửi email. Vui lòng thử lại sau.' . $detail],
            ]);
        }
    }


    public function verifyOtpAndCreateUser(string $inputOtp): User
    {
        $registration = session('registration');

        if (!$registration) {
            throw ValidationException::withMessages([
                'otp' => ['Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.'],
            ]);
        }

        if (now()->greaterThan(\Carbon\Carbon::parse($registration['otp_expires_at']))) {
            throw ValidationException::withMessages([
                'otp' => ['Mã xác thực đã hết hạn. Vui lòng gửi lại mã mới.'],
            ]);
        }

        if ((string) $inputOtp !== (string) $registration['otp']) {
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
            // Handle unique constraint violation (race condition)
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
        $registration['otp_expires_at'] = now()->addMinutes(5)->toISOString();
        session(['registration' => $registration]);

        try {
            Mail::to($registration['email'])->send(
                new VerificationCodeMail($otp, $registration['name'])
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi gửi lại mail OTP: ' . $e->getMessage(), [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            $detail = config('app.debug') ? ' [DEBUG: ' . get_class($e) . ': ' . $e->getMessage() . ']' : '';
            throw ValidationException::withMessages([
                'otp' => ['Hệ thống đang gặp sự cố khi gửi email. Vui lòng thử lại sau.' . $detail],
            ]);
        }
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
                'email' => ['Email hoặc mật khẩu không đúng.'],
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
