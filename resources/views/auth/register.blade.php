<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đăng ký | {{ config('app.name', 'Note App') }}</title>
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
                                <p class="hero-label">Get started</p>
                                <h1 class="hero-title">Tạo tài khoản và bắt đầu ghi chú ngay.</h1>
                                <p class="hero-desc">Tổ chức ý tưởng, quản lý công việc và lưu trữ mọi thứ quan trọng — tất cả trong một nơi duy nhất.</p>
                            </div>
                        </div>
                        <div class="info-card">
                            <p class="info-card-title">Bảo mật tài khoản</p>
                            <p class="info-card-text">Chúng tôi sẽ gửi mã xác thực 6 số qua email để xác minh tài khoản của bạn.</p>
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
                            <p class="section-label">Create account</p>
                            <h2 class="section-title">Tạo tài khoản mới</h2>
                            <p class="section-desc">Điền thông tin bên dưới để đăng ký tài khoản và bắt đầu sử dụng.</p>
                        </div>

                        @if ($errors->any())
                            <div class="alert-error">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register') }}" class="auth-form">
                            @csrf

                            <div class="form-group">
                                <label for="name">Họ và tên</label>
                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    autofocus
                                    class="form-control"
                                    placeholder="Nguyễn Văn A"
                                >
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    class="form-control"
                                    placeholder="you@example.com"
                                >
                            </div>

                            <div class="form-group">
                                <label for="password">Mật khẩu</label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    class="form-control"
                                    placeholder="Nhập mật khẩu"
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
                                Đăng ký
                            </button>
                        </form>

                        <p class="auth-footer">
                            Đã có tài khoản?
                            <a href="{{ route('login') }}">Đăng nhập</a>
                        </p>
                    </div>
                </section>
            </div>
        </main>

        <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    </body>
</html>
