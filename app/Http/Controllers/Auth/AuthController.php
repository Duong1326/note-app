<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $this->authService->initiateRegistration($request->validated());

        return redirect()->route('verify.otp');
    }

    public function showVerifyOtp()
    {
        // Only allow access if there is an active registration session
        if (!session('registration')) {
            return redirect()->route('register')
                ->with('error', 'Vui lòng đăng ký trước.');
        }

        return view('auth.verify-otp', [
            'email' => session('registration.email'),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->authService->verifyOtpAndCreateUser($request->otp);

        return redirect()->route('login')->with('success', 'Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.');
    }

    public function resendOtp()
    {
        $this->authService->resendOtp();

        return back()->with('success', 'Mã xác thực mới đã được gửi!');
    }

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login($request->validated(), $request->boolean('remember'));

        return redirect()->intended(route('dashboard'))->with('success', 'Đăng nhập thành công!');
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->route('login')->with('success', 'Đăng xuất thành công!');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function redirectHome()
    {
        return redirect()->route('login');
    }
}