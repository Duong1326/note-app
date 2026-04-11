<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;

class AuthControler extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());

        auth()->login($user);
        return redirect()->route('home');
    }

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login($request->validated());

        auth()->login($user);
        return redirect()->route('home');
    }
}