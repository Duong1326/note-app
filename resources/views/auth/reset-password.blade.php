<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đặt lại mật khẩu | {{ config('app.name', 'LiveNote') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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
                <p class="brand-subtitle">Tạo mật khẩu mới cho tài khoản <strong>{{ $email }}</strong></p>
            </div>

            {{-- Error Alert --}}
            @if ($errors->any())
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Reset Password Form --}}
            <form method="POST" action="{{ route('password.update') }}" class="auth-form" id="reset-password-form">
                @csrf

                <div class="form-group">
                    <label for="password">Mật khẩu mới</label>
                    <div class="input-wrapper">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autofocus
                            class="form-input input-password"
                            placeholder="Nhập 6 số"
                        >
                        <span class="material-symbols-outlined input-icon">lock</span>
                        <button type="button" class="toggle-password-btn" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display: none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Xác nhận mật khẩu</label>
                    <div class="input-wrapper">
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            class="form-input input-password"
                            placeholder="Nhập lại mật khẩu"
                        >
                        <span class="material-symbols-outlined input-icon">lock_reset</span>
                        <button type="button" class="toggle-password-btn" onclick="togglePassword('password_confirmation', this)" aria-label="Toggle password visibility">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display: none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="reset-password-btn">
                    <span class="material-symbols-outlined">lock_open</span>
                    <span>Đặt lại mật khẩu</span>
                </button>
            </form>

            {{-- Footer --}}
            <p class="auth-footer">
                Nhớ mật khẩu rồi?
                <a href="{{ route('login') }}">Quay lại đăng nhập</a>
            </p>
        </div>
    </main>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const eyeIcon = btn.querySelector('.eye-icon');
            const eyeOffIcon = btn.querySelector('.eye-off-icon');

            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>
