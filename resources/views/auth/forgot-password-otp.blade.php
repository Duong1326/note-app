<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác thực OTP | {{ config('app.name', 'Fluid Notes') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
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
                            <p class="hero-label">Xác minh</p>
                            <h1 class="hero-title">Xác minh danh tính để đặt lại mật khẩu.</h1>
                            <p class="hero-desc">Nhập mã xác thực đã gửi đến email của bạn để tiếp tục đặt lại mật khẩu.
                            </p>
                        </div>
                    </div>
                    <div class="info-card">
                        <p class="info-card-title">Mã bảo mật</p>
                        <p class="info-card-text">Mã xác thực có hiệu lực trong 5 phút. Không chia sẻ mã này với ai.</p>
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

                    <div class="mt-4 text-center">
                        <div class="d-flex justify-content-center mb-4">
                            <div class="verify-icon">
                                <span class="material-symbols-outlined">lock_reset</span>
                            </div>
                        </div>
                        <h2 class="verify-title">Nhập mã xác thực</h2>
                        <p class="verify-description">
                            Chúng tôi đã gửi mã 6 số đến email
                            <strong>{{ $email }}</strong>.
                            Vui lòng nhập mã bên dưới để đặt lại mật khẩu.
                        </p>
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

                    <form method="POST" action="{{ route('password.verify.otp.submit') }}" class="auth-form mt-4"
                        id="otp-form">
                        @csrf
                        <input type="hidden" name="otp" id="otp-hidden">

                        <div class="otp-inputs" id="otp-inputs">
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                autocomplete="one-time-code" data-index="0" autofocus>
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                data-index="1">
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                data-index="2">
                            <div class="otp-separator"><span></span></div>
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                data-index="3">
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                data-index="4">
                            <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                data-index="5">
                        </div>

                        <div class="otp-timer" id="otp-timer">
                            <span class="material-symbols-outlined">timer</span>
                            <span>Mã hết hạn sau <strong id="countdown">5:00</strong></span>
                        </div>

                        <button type="submit" class="btn btn-dark btn-auth w-100" id="verify-btn" disabled>
                            Xác thực
                        </button>
                    </form>

                    <div class="otp-resend text-center mt-3 small text-muted">
                        <span>Không nhận được mã?</span>
                        <form method="POST" action="{{ route('password.resend.otp') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="link-btn">Gửi lại mã</button>
                        </form>
                    </div>

                    <p class="auth-footer mt-4 small text-muted text-center">
                        Nhớ mật khẩu rồi?
                        <a href="{{ route('login') }}">Quay lại đăng nhập</a>
                    </p>
                </div>
            </section>
        </div>
    </main>

    <script>
        (function () {
            const inputs = document.querySelectorAll('.otp-box');
            const hiddenInput = document.getElementById('otp-hidden');
            const submitBtn = document.getElementById('verify-btn');
            const form = document.getElementById('otp-form');

            function updateHiddenInput() {
                let otp = '';
                inputs.forEach(input => otp += input.value);
                hiddenInput.value = otp;
                submitBtn.disabled = otp.length < 6;
            }

            inputs.forEach((input, index) => {
                input.addEventListener('input', function (e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    updateHiddenInput();
                    if (hiddenInput.value.length === 6) {
                        submitBtn.disabled = false;
                        form.submit();
                    }
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                        inputs[index - 1].value = '';
                        updateHiddenInput();
                    }
                });

                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData)
                        .getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                    if (pastedData.length > 0) {
                        for (let i = 0; i < pastedData.length && i < inputs.length; i++) {
                            inputs[i].value = pastedData[i];
                        }
                        const nextIndex = Math.min(pastedData.length, inputs.length - 1);
                        inputs[nextIndex].focus();
                        updateHiddenInput();
                        if (pastedData.length === 6) form.submit();
                    }
                });

                input.addEventListener('focus', function () { this.select(); });
            });

            const countdownEl = document.getElementById('countdown');
            const timerEl = document.getElementById('otp-timer');
            let totalSeconds = 5 * 60;

            const timer = setInterval(() => {
                totalSeconds--;
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                if (totalSeconds <= 60) timerEl.classList.add('timer-warning');
                if (totalSeconds <= 0) {
                    clearInterval(timer);
                    countdownEl.textContent = 'Hết hạn';
                    timerEl.classList.add('timer-expired');
                    submitBtn.disabled = true;
                }
            }, 1000);
        })();
    </script>

</body>

</html>