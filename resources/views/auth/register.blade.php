<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký | {{ config('app.name', 'Fluid Notes') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" />
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
</head>

<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center py-5 px-3">
        <div class="auth-card">
            <section class="auth-panel-left">
                <div class="panel-left-content">
                    <div>
                        <a href="{{ route('home') }}" class="brand-link">
                            <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 24px; width: auto;">
                            {{ config('app.name', 'Fluid Notes') }}
                        </a>
                        <div class="hero-section mt-5">
                            <p class="hero-label">Bắt đầu</p>
                            <h1 class="hero-title">Tạo tài khoản và bắt đầu ghi chú ngay.</h1>
                            <p class="hero-desc">Tổ chức ý tưởng, quản lý công việc và lưu trữ mọi thứ quan trọng — tất
                                cả trong một nơi duy nhất.</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <p class="info-card-title">Bảo mật tài khoản</p>
                        <p class="info-card-text">Chúng tôi sẽ gửi mã xác thực 6 số qua email để xác minh tài khoản của
                            bạn.</p>
                    </div>
                </div>
            </section>

            <section class="auth-panel-right">
                <div class="form-wrapper">
                    <div class="mobile-brand">
                        <a href="{{ route('home') }}">
                            <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 24px; width: auto;">
                            {{ config('app.name', 'Fluid Notes') }}
                        </a>
                    </div>

                    <div class="mt-4">
                        <p class="section-label">Tạo tài khoản</p>
                        <h2 class="section-title">Tạo tài khoản mới</h2>
                        <p class="section-desc">Điền thông tin bên dưới để đăng ký tài khoản và bắt đầu sử dụng.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert auth-alert-error mt-3 py-2 px-3 small">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="auth-form mt-4">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label fw-medium small">Họ và tên</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                                class="form-control" placeholder="Nguyễn Văn A">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium small">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                class="form-control" placeholder="you@example.com">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium small">Mật khẩu</label>
                            <div class="auth-pw-wrap">
                                <input id="password" type="password" name="password" required class="form-control"
                                    placeholder="Nhập mật khẩu">
                                <button type="button" class="auth-pw-eye" onclick="toggleAuthPw('password', this)"
                                    tabindex="-1" aria-label="Hiện mật khẩu">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                            <p class="password-hint">Mật khẩu gồm tối thiểu 6 ký tự</p>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label fw-medium small">Xác nhận mật
                                khẩu</label>
                            <div class="auth-pw-wrap">
                                <input id="password_confirmation" type="password" name="password_confirmation" required
                                    class="form-control" placeholder="Nhập lại mật khẩu">
                                <button type="button" class="auth-pw-eye"
                                    onclick="toggleAuthPw('password_confirmation', this)" tabindex="-1"
                                    aria-label="Hiện mật khẩu">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-auth w-100">
                            Đăng ký
                        </button>
                    </form>

                    <p class="auth-footer mt-4 small text-muted">
                        Đã có tài khoản?
                        <a href="{{ route('login') }}">Đăng nhập</a>
                    </p>
                </div>
            </section>
        </div>
    </main>

    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        function toggleAuthPw(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = isHidden ? 'visibility_off' : 'visibility';
        }
    </script>
</body>

</html>