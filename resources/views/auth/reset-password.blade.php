<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu | {{ config('app.name', 'Fluid Notes') }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">
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
                            <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 32px; width: auto;">
                            {{ config('app.name', 'Fluid Notes') }}
                        </a>
                        <div class="hero-section mt-5">
                            <p class="hero-label">Mật khẩu mới</p>
                            <h1 class="hero-title">Tạo mật khẩu mới cho tài khoản của bạn.</h1>
                            <p class="hero-desc">Chọn một mật khẩu mới và đăng nhập lại để tiếp tục sử dụng.</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <p class="info-card-title">Lưu ý</p>
                        <p class="info-card-text">Mật khẩu mới gồm tối thiểu 6 ký tự, chữ hoa, chữ thường, số và ký tự đặc biệt.</p>
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
                        <p class="section-label">Đặt lại mật khẩu</p>
                        <h2 class="section-title">Đặt mật khẩu mới</h2>
                        <p class="section-desc">Tạo mật khẩu mới cho tài khoản <strong>{{ $email }}</strong></p>
                    </div>

                    @if ($errors->any())
                        <div class="alert auth-alert-error mt-3 py-2 px-3 small">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}" class="auth-form mt-4">
                        @csrf

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium small">Mật khẩu mới</label>
                            <div class="auth-pw-wrap">
                                <input id="password" type="password" name="password" required autofocus
                                    class="form-control" placeholder="Nhập mật khẩu mới">
                                <button type="button" class="auth-pw-eye" onclick="toggleAuthPw('password', this)"
                                    tabindex="-1" aria-label="Hiện mật khẩu">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                            <p class="password-hint">Mật khẩu gồm tối thiểu 6 ký tự, chữ hoa, chữ thường, số và ký tự đặc biệt</p>
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
                            Đặt lại mật khẩu
                        </button>
                    </form>

                    <p class="auth-footer mt-4 small text-muted">
                        Nhớ mật khẩu rồi?
                        <a href="{{ route('login') }}">Quay lại đăng nhập</a>
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