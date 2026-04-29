<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quên mật khẩu | {{ config('app.name', 'Fluid Notes') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
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
                            {{ config('app.name', 'Fluid Notes') }}
                        </a>
                        <div class="hero-section mt-5">
                            <p class="hero-label">Khôi phục mật khẩu</p>
                            <h1 class="hero-title">Đặt lại mật khẩu dễ dàng và nhanh chóng.</h1>
                            <p class="hero-desc">Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác thực để bạn tạo mật khẩu
                                mới.</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <p class="info-card-title">An toàn & bảo mật</p>
                        <p class="info-card-text">Mã xác thực sẽ được gửi đến email của bạn và hết hạn sau 5 phút.</p>
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
                        <p class="section-label">Khôi phục mật khẩu</p>
                        <h2 class="section-title">Quên mật khẩu?</h2>
                        <p class="section-desc">Nhập email để nhận mã xác thực đặt lại mật khẩu.</p>
                    </div>

                    @if (session('success'))
                        <div class="alert auth-alert-success mt-3 py-2 px-3 small">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert auth-alert-error mt-3 py-2 px-3 small">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="auth-form mt-4">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium small">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                class="form-control" placeholder="you@example.com">
                        </div>

                        <button type="submit" class="btn btn-dark btn-auth w-100">
                            Gửi mã xác thực
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