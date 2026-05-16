<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Tạo một tài khoản test để người dùng đăng nhập ngay
        User::updateOrCreate(
            ['email' => 'test@gmail.com'],
            [
                'name' => 'Test',
                'password' => 'Test@123',
                'email_verified_at' => now(),   // Đã xác thực email để bỏ qua OTP
            ]
        );
    }
}
