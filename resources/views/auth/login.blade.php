<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đăng nhập | {{ config('app.name', 'Note App') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
        <style>
            :root {
                --sky-50: #f0f9ff;
                --sky-100: #e0f2fe;
                --sky-500: #0ea5e9;
                --sky-600: #0284c7;
                --sky-700: #0369a1;
                --sky-900: #0c4a6e;
                --slate-50: #f8fafc;
                --slate-200: #e2e8f0;
                --slate-400: #94a3b8;
                --slate-500: #64748b;
                --slate-600: #475569;
                --slate-700: #334155;
                --slate-900: #0f172a;
                --slate-950: #020617;
                --emerald-50: #ecfdf5;
                --emerald-200: #a7f3d0;
                --emerald-700: #047857;
                --rose-50: #fff1f2;
                --rose-200: #fecdd3;
                --rose-700: #be123c;
                --cyan-300: #67e8f9;
                --amber-300: #fcd34d;
            }

            * { box-sizing: border-box; }

            body {
                font-family: 'Instrument Sans', sans-serif;
                color: var(--slate-900);
                min-height: 100vh;
                background:
                    radial-gradient(circle at top, #fef3c7, transparent 35%),
                    linear-gradient(135deg, #f8fafc 0%, #e0f2fe 45%, #ecfccb 100%);
                margin: 0;
            }

            .auth-main {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2.5rem 1rem;
            }

            .auth-card {
                width: 100%;
                max-width: 72rem;
                display: grid;
                grid-template-columns: 1fr;
                overflow: hidden;
                border-radius: 2rem;
                border: 1px solid rgba(255, 255, 255, 0.6);
                background: rgba(255, 255, 255, 0.75);
                backdrop-filter: blur(16px);
                box-shadow: 0 24px 90px rgba(15, 23, 42, 0.12);
            }

            @media (min-width: 1200px) {
                .auth-card {
                    grid-template-columns: 1.1fr 0.9fr;
                }
            }

            /* Left Panel */
            .auth-panel-left {
                display: none;
                position: relative;
                overflow: hidden;
                background: var(--slate-950);
                color: #fff;
                padding: 3rem 2.5rem;
            }

            @media (min-width: 1200px) {
                .auth-panel-left {
                    display: block;
                }
            }

            .auth-panel-left::before {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(circle at top left, rgba(56, 189, 248, 0.35), transparent 30%),
                    radial-gradient(circle at bottom right, rgba(251, 191, 36, 0.28), transparent 32%);
            }

            .panel-left-content {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                height: 100%;
            }

            .brand-link {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.8);
                text-decoration: none;
                transition: color 0.2s;
            }

            .brand-link:hover {
                color: #fff;
            }

            .brand-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 50%;
                border: 1px solid rgba(255, 255, 255, 0.15);
                background: rgba(255, 255, 255, 0.1);
                font-weight: 600;
            }

            .hero-section {
                margin-top: 4rem;
                max-width: 28rem;
            }

            .hero-label {
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.35em;
                color: rgba(103, 232, 249, 0.8);
            }

            .hero-title {
                margin-top: 1rem;
                font-size: 2.25rem;
                font-weight: 600;
                line-height: 1.2;
            }

            .hero-desc {
                margin-top: 1.5rem;
                font-size: 1rem;
                line-height: 1.75;
                color: rgba(255, 255, 255, 0.7);
            }

            .info-card {
                border-radius: 1rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
                padding: 1.25rem;
                margin-top: 2rem;
            }

            .info-card-title {
                font-weight: 500;
                color: #fff;
                margin-bottom: 0.5rem;
            }

            .info-card-text {
                font-size: 0.875rem;
                line-height: 1.5;
                color: rgba(255, 255, 255, 0.75);
            }

            /* Right Panel */
            .auth-panel-right {
                padding: 2rem 1.25rem;
            }

            @media (min-width: 576px) {
                .auth-panel-right {
                    padding: 3rem 2.5rem;
                }
            }

            .form-wrapper {
                max-width: 28rem;
                margin: 0 auto;
            }

            .mobile-brand {
                display: block;
            }

            @media (min-width: 1200px) {
                .mobile-brand {
                    display: none;
                }
            }

            .mobile-brand a {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--slate-600);
                text-decoration: none;
                transition: color 0.2s;
            }

            .mobile-brand a:hover {
                color: var(--slate-900);
            }

            .mobile-brand-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 50%;
                background: var(--slate-900);
                color: #fff;
                font-weight: 600;
            }

            .section-label {
                font-size: 0.875rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.3em;
                color: var(--sky-700);
            }

            .section-title {
                margin-top: 0.75rem;
                font-size: 1.875rem;
                font-weight: 600;
                letter-spacing: -0.025em;
                color: var(--slate-900);
            }

            .section-desc {
                margin-top: 0.75rem;
                font-size: 0.875rem;
                line-height: 1.5;
                color: var(--slate-500);
            }

            .alert-success {
                margin-top: 1.5rem;
                border-radius: 1rem;
                border: 1px solid var(--emerald-200);
                background: var(--emerald-50);
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
                color: var(--emerald-700);
            }

            .alert-error {
                margin-top: 1.5rem;
                border-radius: 1rem;
                border: 1px solid var(--rose-200);
                background: var(--rose-50);
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
                color: var(--rose-700);
            }

            .auth-form {
                margin-top: 2rem;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--slate-700);
            }

            .form-group .form-control {
                width: 100%;
                border-radius: 1rem;
                border: 1px solid var(--slate-200);
                background: #fff;
                padding: 0.75rem 1rem;
                font-size: 0.9375rem;
                color: var(--slate-900);
                outline: none;
                transition: border-color 0.2s, box-shadow 0.2s;
                font-family: inherit;
            }

            .form-group .form-control::placeholder {
                color: var(--slate-400);
            }

            .form-group .form-control:focus {
                border-color: var(--sky-500);
                box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
            }

            .password-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 0.5rem;
            }

            .remember-wrapper {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                border-radius: 1rem;
                border: 1px solid var(--slate-200);
                background: var(--slate-50);
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
                color: var(--slate-600);
                margin-bottom: 1.25rem;
            }

            .remember-wrapper input[type="checkbox"] {
                width: 1rem;
                height: 1rem;
                accent-color: var(--sky-600);
            }

            .btn-login {
                display: inline-flex;
                width: 100%;
                align-items: center;
                justify-content: center;
                border-radius: 1rem;
                background: var(--slate-950);
                color: #fff;
                padding: 0.875rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 600;
                border: none;
                cursor: pointer;
                transition: background-color 0.2s, box-shadow 0.2s;
                font-family: inherit;
            }

            .btn-login:hover {
                background: var(--sky-700);
            }

            .btn-login:focus {
                outline: none;
                box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.2);
            }

            .register-link {
                margin-top: 2rem;
                font-size: 0.875rem;
                color: var(--slate-500);
            }

            .register-link a {
                font-weight: 600;
                color: var(--sky-700);
                text-decoration: none;
                transition: color 0.2s;
            }

            .register-link a:hover {
                color: var(--sky-900);
            }
        </style>
    </head>
    <body>
        <main class="auth-main">
            <div class="auth-card">
                <section class="auth-panel-left">
                    <div class="panel-left-content">
                        <div>
                            <a href="{{ route('home') }}" class="brand-link">
                                <span class="brand-icon">N</span>
                                {{ config('app.name', 'Note App') }}
                            </a>
                            <div class="hero-section">
                                <p class="hero-label">Welcome back</p>
                                <h1 class="hero-title">Đăng nhập để tiếp tục quản lý ghi chú của bạn.</h1>
                                <p class="hero-desc">Không gian làm việc gọn gàng hơn khi mọi ý tưởng, checklist và nhắc việc đều nằm đúng chỗ.</p>
                            </div>
                        </div>
                        <div class="info-card">
                            <p class="info-card-title">Đăng nhập nhanh</p>
                            <p class="info-card-text">Hệ thống sẽ giữ phiên đăng nhập nếu bạn chọn "Ghi nhớ tôi".</p>
                        </div>
                    </div>
                </section>

                <section class="auth-panel-right">
                    <div class="form-wrapper">
                        <div class="mobile-brand">
                            <a href="{{ route('home') }}">
                                <span class="mobile-brand-icon">N</span>
                                {{ config('app.name', 'Note App') }}
                            </a>
                        </div>

                        <div class="mt-4">
                            <p class="section-label">Sign in</p>
                            <h2 class="section-title">Chào mừng bạn quay lại</h2>
                            <p class="section-desc">Đăng nhập để tiếp tục truy cập ghi chú, nhãn và công việc đang theo dõi.</p>
                        </div>

                        @if (session('success'))
                            <div class="alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert-error">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="auth-form">
                            @csrf

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    class="form-control"
                                    placeholder="you@example.com"
                                >
                            </div>

                            <div class="form-group">
                                <div class="password-header">
                                    <label for="password" class="mb-0">Mật khẩu</label>
                                </div>
                                <div style="position: relative;">
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        class="form-control"
                                        style="padding-right: 3rem;"
                                        placeholder="Nhập mật khẩu"
                                    >
                                    <button type="button" onclick="togglePassword('password', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--slate-400); padding: 0.25rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off-icon" style="display: none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                                    </button>
                                </div>
                            </div>

                            <label class="remember-wrapper">
                                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                                Ghi nhớ tôi trên thiết bị này
                            </label>

                            <button type="submit" class="btn-login">
                                Đăng nhập
                            </button>
                        </form>

                        <p class="register-link">
                            Chưa có tài khoản?
                            <a href="{{ route('register') }}">Tạo tài khoản mới</a>
                        </p>
                    </div>
                </section>
            </div>
        </main>

        <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
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
