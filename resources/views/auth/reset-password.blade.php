<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đặt lại mật khẩu | {{ config('app.name', 'Note App') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
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
                                <p class="hero-label">New password</p>
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
                                <span class="mobile-brand-icon">N</span>
                                {{ config('app.name', 'Note App') }}
                            </a>
                        </div>

                        <div class="mt-4">
                            <p class="section-label">Reset password</p>
                            <h2 class="section-title">Đặt mật khẩu mới</h2>
                            <p class="section-desc">Tạo mật khẩu mới cho tài khoản <strong>{{ $email }}</strong></p>
                        </div>

                        @if ($errors->any())
                            <div class="alert-error">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.update') }}" class="auth-form">
                            @csrf

                            <div class="form-group">
                                <label for="password">Mật khẩu mới</label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autofocus
                                    class="form-control"
                                    placeholder="Nhập mật khẩu mới"
                                >
                                <p class="password-hint">Mật khẩu gồm tối thiểu 6 ký tự</p>
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Xác nhận mật khẩu</label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    class="form-control"
                                    placeholder="Nhập lại mật khẩu"
                                >
                            </div>

                            <button type="submit" class="btn-submit">
                                Đặt lại mật khẩu
                            </button>
                        </form>

                        <p class="auth-footer">
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
