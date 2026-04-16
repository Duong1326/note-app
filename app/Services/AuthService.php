<?php

namespace App\Services;

use App\Models\User;
use Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'email' => ['Email has already been taken.'],
                ]);
            }

            throw $e;
        }

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
