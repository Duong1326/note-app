<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{

    public function showForm()
    {
        $reset = session('password_reset');

        if (!$reset || !($reset['verified'] ?? false)) {
            return redirect()->route('password.request')
                ->with('error', 'Vui lòng xác thực OTP trước.');
        }

        return view('auth.reset-password', [
            'email' => $reset['email'],
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'password' => ['required', 'min:6', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_]).+$/', 'confirmed'],
        ], [
            'password.regex' => 'Mật khẩu phải chứa ít nhất một chữ hoa, một chữ thường và một ký tự đặc biệt.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $reset = session('password_reset');

        if (!$reset || !($reset['verified'] ?? false)) {
            return redirect()->route('password.request')
                ->with('error', 'Phiên đã hết hạn. Vui lòng thử lại.');
        }

        $user = User::where('email', $reset['email'])->first();

        if (!$user) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Không tìm thấy tài khoản.']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clear session
        session()->forget('password_reset');

        return redirect()->route('login')
            ->with('success', 'Mật khẩu đã được đặt lại thành công!');
    }
}
