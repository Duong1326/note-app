<?php

namespace App\Services;

use App\Models\User;
use Auth;
use Illuminate\Auth\Events\Registered;

class AuthService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        event(new Registered($user));
        return $user;
    }

    public function login(array $credentials, bool $remember = false): User
    {
        if (
            !Auth::attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ], $remember)
        ) {
            throw ValidateException::withMessages([
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