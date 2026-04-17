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
            'password' => ['required', 'min:6', 'regex:/^\d{6,}$/', 'confirmed'],
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

        // Xóa session
        session()->forget('password_reset');

        return redirect()->route('login')
            ->with('success', 'Mật khẩu đã được đặt lại thành công!');
    }
}
