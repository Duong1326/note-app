<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    // ──────────────────────────────────────────────
    // Show profile page
    // ──────────────────────────────────────────────

    public function show(): View
    {
        return view('profile');
    }

    // ──────────────────────────────────────────────
    // Update name & bio
    // ──────────────────────────────────────────────

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bio'  => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $user->update($request->only('name', 'bio'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'name'    => $user->name,
            ]);
        }

        return back()->with('success', 'Cập nhật thông tin thành công!');
    }

    // ──────────────────────────────────────────────
    // Upload avatar via Cloudinary
    // ──────────────────────────────────────────────

    public function updateAvatar(Request $request, CloudinaryService $cloudinary): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ], [
            'avatar.image' => 'File phải là ảnh.',
            'avatar.mimes' => 'Ảnh phải có định dạng: jpeg, jpg, png, gif, webp.',
            'avatar.max'   => 'Kích thước ảnh không được vượt quá 10MB.',
        ]);

        $user = $request->user();

        // Delete old avatar from Cloudinary if exists
        if ($user->avatar_public_id) {
            $cloudinary->deleteImage($user->avatar_public_id);
        }

        $result = $cloudinary->uploadAvatar($request->file('avatar'), $user->id);

        $user->update([
            'avatar_url'       => $result['secure_url'],
            'avatar_public_id' => $result['public_id'],
        ]);

        // Touch updated_at so avatarUrl() cache-busting always triggers
        $user->touch();

        return response()->json([
            'success'    => true,
            'avatar_url' => $user->avatarUrl(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Change account password
    // ──────────────────────────────────────────────

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\W_]).+$/', 'confirmed'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required'         => 'Vui lòng nhập mật khẩu mới.',
            'password.min'              => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.regex'            => 'Mật khẩu phải chứa ít nhất một chữ hoa, một chữ thường và một ký tự đặc biệt.',
            'password.confirmed'        => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => ['current_password' => ['Mật khẩu hiện tại không đúng.']],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công!',
        ]);
    }
}
