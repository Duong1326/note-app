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
        User::factory()->create([
            'name' => 'Tài khoản Test',
            'email' => 'test@gmail.com',
            'password' => '123456', // Laravel 11 sẽ tự động hash (do casts 'hashed')
            'email_verified_at' => now(),   // Đã xác thực email để bỏ qua OTP
        ]);
    }
}
