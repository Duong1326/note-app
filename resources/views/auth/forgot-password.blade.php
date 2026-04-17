<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quên mật khẩu | {{ config('app.name', 'LiveNote') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
</head>

<body class="auth-page">
    <div class="auth-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <main class="auth-main">
        <div class="auth-card">

            {{-- Brand Header --}}
            <div class="brand-header">
                <a href="{{ route('home') }}" class="brand-logo">
                    <div class="brand-logo-icon">
                        <span class="material-symbols-outlined">edit_note</span>
                    </div>
                    <span class="brand-logo-text">LiveNote</span>
                </a>
                <p class="brand-subtitle">Nhập email để nhận mã xác thực</p>
            </div>

            {{-- Success Alert --}}
            @if (session('success'))
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            {{-- Error Alert --}}
            @if ($errors->any())
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Forgot Password Form --}}
            <form method="POST" action="{{ route('password.email') }}" class="auth-form" id="forgot-password-form">
                @csrf

                <div class="form-group">
                    <label for="email">Địa chỉ Email</label>
                    <div class="input-wrapper">
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                            class="form-input" placeholder="you@example.com">
                        <span class="material-symbols-outlined input-icon">mail</span>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="forgot-password-btn">
                    <span class="material-symbols-outlined">lock_reset</span>
                    <span>Gửi mã xác thực</span>
                </button>
            </form>

            <p class="auth-footer">
                Nhớ mật khẩu rồi?
                <a href="{{ route('login') }}">Quay lại đăng nhập</a>
            </p>
        </div>
    </main>
</body>

</html>