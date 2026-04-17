<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xác thực OTP | {{ config('app.name', 'LiveNote') }}</title>
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
            </div>

            {{-- Email Icon --}}
            <div class="verify-icon-wrapper">
                <div class="verify-icon">
                    <span class="material-symbols-outlined">mail_lock</span>
                </div>
            </div>

            {{-- Title --}}
            <h2 class="verify-title">Nhập mã xác thực</h2>
            <p class="verify-description">
                Chúng tôi đã gửi mã 6 số đến email
                <strong>{{ $email }}</strong>.
                Vui lòng kiểm tra hộp thư và nhập mã bên dưới.
            </p>

            {{-- Success Alert --}}
            @if (session('success'))
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            {{-- Error Alert --}}
            @if ($errors->any())
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- OTP Form --}}
            <form method="POST" action="{{ route('verify.otp.submit') }}" class="auth-form" id="otp-form">
                @csrf
                <input type="hidden" name="otp" id="otp-hidden">

                <div class="otp-inputs" id="otp-inputs">
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" data-index="0" autofocus>
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="1">
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="2">
                    <div class="otp-separator">
                        <span></span>
                    </div>
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="3">
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="4">
                    <input type="text" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" data-index="5">
                </div>

                {{-- Countdown timer --}}
                <div class="otp-timer" id="otp-timer">
                    <span class="material-symbols-outlined">timer</span>
                    <span>Mã hết hạn sau <strong id="countdown">5:00</strong></span>
                </div>

                <button type="submit" class="btn-submit" id="verify-btn" disabled>
                    <span class="material-symbols-outlined">verified</span>
                    <span>Xác thực</span>
                </button>
            </form>

            {{-- Resend --}}
            <div class="otp-resend">
                <span>Không nhận được mã?</span>
                <form method="POST" action="{{ route('verify.otp.resend') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="link-btn" id="resend-btn">Gửi lại mã</button>
                </form>
            </div>

            {{-- Hint --}}
            <div class="verify-hint">
                <span class="material-symbols-outlined">info</span>
                <span>Kiểm tra thư mục Spam nếu không thấy email.</span>
            </div>

        </div>
    </main>

    <script>
    (function() {
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
            // Chỉ cho phép nhập số
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                updateHiddenInput();

                // Auto-submit khi nhập đủ 6 số
                if (hiddenInput.value.length === 6) {
                    submitBtn.disabled = false;
                    form.submit();
                }
            });

            // Backspace
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                    updateHiddenInput();
                }
            });

            // Paste support
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData)
                    .getData('text')
                    .replace(/[^0-9]/g, '')
                    .slice(0, 6);

                if (pastedData.length > 0) {
                    for (let i = 0; i < pastedData.length && i < inputs.length; i++) {
                        inputs[i].value = pastedData[i];
                    }
                    const nextIndex = Math.min(pastedData.length, inputs.length - 1);
                    inputs[nextIndex].focus();
                    updateHiddenInput();

                    if (pastedData.length === 6) {
                        form.submit();
                    }
                }
            });

            // Focus select
            input.addEventListener('focus', function() {
                this.select();
            });
        });

        // Countdown timer (5 phút)
        const countdownEl = document.getElementById('countdown');
        const timerEl = document.getElementById('otp-timer');
        let totalSeconds = 5 * 60;

        const timer = setInterval(() => {
            totalSeconds--;
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (totalSeconds <= 60) {
                timerEl.classList.add('timer-warning');
            }

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
