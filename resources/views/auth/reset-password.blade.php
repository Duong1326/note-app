<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu | {{ config('app.name', 'Fluid Notes') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/auth.css'])
</head>

<body>
    <main class="min-vh-100 d-flex align-items-center justify-content-center py-5 px-3">
        <div class="auth-card">
            <section class="auth-panel-left">
                <div class="panel-left-content">
                    <div>
                        <a href="{{ route('home') }}" class="brand-link">
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
                        <p class="info-card-text">Mật khẩu mới phải có tối thiểu 6 ký tự.</p>
                    </div>
                </div>
            </section>

            <section class="auth-panel-right">
                <div class="form-wrapper">
                    <div class="mobile-brand">
                        <a href="{{ route('home') }}">
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
                            <input id="password" type="password" name="password" required autofocus class="form-control"
                                placeholder="Nhập mật khẩu mới">
                            <p class="password-hint">Mật khẩu gồm tối thiểu 6 ký tự</p>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label fw-medium small">Xác nhận mật
                                khẩu</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                class="form-control" placeholder="Nhập lại mật khẩu">
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
</body>

</html>