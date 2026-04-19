<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Đăng nhập | {{ config('app.name', 'Note App') }}</title>
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
                                    <a href="{{ route('password.request') }}" class="forgot-link">Quên mật khẩu?</a>
                                </div>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    class="form-control"
                                    placeholder="Nhập mật khẩu"
                                >
                            </div>

                            <label class="remember-wrapper">
                                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                                Ghi nhớ tôi trên thiết bị này
                            </label>

                            <button type="submit" class="btn-submit">
                                Đăng nhập
                            </button>
                        </form>

                        <p class="auth-footer">
                            Chưa có tài khoản?
                            <a href="{{ route('register') }}">Tạo tài khoản mới</a>
                        </p>
                    </div>
                </section>
            </div>
        </main>

        <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    </body>
</html>
