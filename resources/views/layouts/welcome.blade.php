<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Fluid Notes') }} — Không gian làm việc cho ghi chú của bạn</title>
    <meta name="description"
        content="Ghi chú, cộng tác và quản lý ý tưởng của bạn — tất cả ở một nơi. Được xây dựng cho cá nhân và nhóm muốn di chuyển nhanh mà không làm mất đi bối cảnh.">
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">
    {{-- Font loaded locally via system stack (no CDN needed) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/welcome.css') }}">
</head>

<body>

    <!-- ════════════════════════════════════ -->
    <!--  HEADER                             -->
    <!-- ════════════════════════════════════ -->
    <header class="wl-header" id="main-header">
        <nav class="container-xl wl-nav" aria-label="Main navigation">

            <!-- Brand -->
            <a href="{{ route('home') }}" class="wl-brand" aria-label="{{ config('app.name') }} home">
                <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 28px; width: auto;">
                {{ config('app.name', 'Fluid Notes') }}
            </a>

            <!-- Nav links (desktop) -->
            <ul class="wl-nav-links" style="display: none;" id="nav-links-desktop">
                <li><a href="#features">Tính năng</a></li>
                <li><a href="mailto:support@fluidnotes.app">Liên hệ</a></li>
            </ul>

            <!-- Actions -->
            <div class="wl-nav-actions">
                <a href="{{ route('login') }}" class="wl-btn-ghost">Đăng nhập</a>
                <a href="{{ route('register') }}" class="wl-btn-primary">
                    Bắt đầu
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-linecap="round" stroke-linejoin="round"
                            stroke-width="2" />
                    </svg>
                </a>
            </div>
        </nav>
    </header>

    <!-- ════════════════════════════════════ -->
    <!--  HERO                               -->
    <!-- ════════════════════════════════════ -->
    <main>
        <section class="wl-hero" aria-label="Hero">
            <div class="container-xl">


                <!-- Headline -->
                <h1 class="wl-hero-title">
                    Ghi chú thông minh<br>cho mọi ý tưởng.
                </h1>

                <p class="wl-hero-desc">
                    Tạo ghi chú, dán nhãn, chia sẻ với nhóm và quản lý công việc —
                    tất cả trong một không gian làm việc gọn gàng, bảo mật và nhanh chóng.
                </p>

                <!-- CTAs -->
                <div class="wl-hero-ctas">
                    <a href="{{ route('register') }}" class="wl-cta-primary" id="hero-cta-register">
                        Bắt đầu miễn phí
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2" />
                        </svg>
                    </a>
                    <a href="{{ route('login') }}" class="wl-cta-secondary" id="hero-cta-login">
                        Đăng nhập
                    </a>
                </div>

                <!-- Product screenshot -->
                <div class="wl-screenshot-wrap">
                    <div class="wl-screenshot-glow" aria-hidden="true"></div>
                    <img src="{{ asset('dashboard.png') }}" alt="Giao diện ứng dụng {{ config('app.name') }}"
                        class="wl-screenshot">
                </div>

            </div>
        </section>

        <!-- ════════════════════════════════════ -->
        <!--  FEATURES                           -->
        <!-- ════════════════════════════════════ -->
        <section class="wl-features" id="features" aria-label="Tính năng">
            <div class="container-xl">

                <p class="wl-section-label">Tính năng</p>
                <h2 class="wl-section-title">Mọi thứ bạn cần để làm việc hiệu quả</h2>
                <p class="wl-section-desc">
                    Bộ công cụ tập trung được thiết kế cho quy trình sáng tạo — từ việc phác thảo ý tưởng
                    đến quản lý và chia sẻ với đồng đội.
                </p>

                <div class="wl-features-grid">

                    <!-- Feature 1 -->
                    <div class="wl-feature-card">
                        <div class="wl-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                            </svg>
                        </div>
                        <h3 class="wl-feature-title">Trình soạn thảo mạnh mẽ</h3>
                        <p class="wl-feature-desc">
                            Viết ghi chú phong phú với hỗ trợ hình ảnh đính kèm, ghim nhanh và khóa ghi chú
                            bằng mật khẩu để bảo vệ nội dung nhạy cảm.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="wl-feature-card">
                        <div class="wl-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                            </svg>
                        </div>
                        <h3 class="wl-feature-title">Nhãn & Phân loại</h3>
                        <p class="wl-feature-desc">
                            Tạo và gán nhãn tuỳ chỉnh cho từng ghi chú. Lọc nhanh theo nhãn để
                            tìm đúng nội dung bạn cần trong tích tắc.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="wl-feature-card">
                        <div class="wl-feature-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                            </svg>
                        </div>
                        <h3 class="wl-feature-title">Chia sẻ & Cộng tác</h3>
                        <p class="wl-feature-desc">
                            Chia sẻ ghi chú hoặc không gian làm việc với thành viên khác theo
                            quyền đọc hoặc chỉnh sửa. Đồng bộ thay đổi theo thời gian thực.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ════════════════════════════════════ -->
        <!--  CALL TO ACTION                     -->
        <!-- ════════════════════════════════════ -->
        <section class="wl-cta-section" aria-label="Kêu gọi hành động">
            <div class="container-xl">
                <h2 class="wl-section-title">Sẵn sàng để bắt đầu?</h2>
                <p class="wl-cta-desc">
                    Tạo tài khoản trong chưa đầy một phút.<br>
                    Miễn phí mãi mãi cho cá nhân.
                </p>
                <div class="wl-cta-buttons">
                    <a href="{{ route('register') }}" class="wl-cta-dark" id="cta-register-btn">
                        Tạo tài khoản
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M13 7l5 5m0 0l-5 5m5-5H6" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2" />
                        </svg>
                    </a>
                    <a href="{{ route('login') }}" class="wl-cta-outline" id="cta-login-btn">
                        Đăng nhập ngay
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- ════════════════════════════════════ -->
    <!--  FOOTER                             -->
    <!-- ════════════════════════════════════ -->
    <footer class="wl-footer" aria-label="Footer">
        <div class="container-xl">

            <div class="wl-footer-grid">

                <!-- Brand column -->
                <div style="grid-column: span 2;">
                    <div class="wl-footer-brand">
                        <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 20px; width: auto;">
                        {{ config('app.name', 'Fluid Notes') }}
                    </div>
                    <p class="wl-footer-tagline">
                        Không gian làm việc ghi chú cộng tác cho cá nhân và nhóm hiện đại.
                    </p>
                    <div class="wl-footer-social">
                        <a href="#" aria-label="GitHub">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Product column -->
                <div>
                    <h4 class="wl-footer-col-title">Sản phẩm</h4>
                    <ul class="wl-footer-links">
                        <li><a href="#features">Tính năng</a></li>
                        <li><a href="{{ route('register') }}">Đăng ký</a></li>
                        <li><a href="{{ route('login') }}">Đăng nhập</a></li>
                    </ul>
                </div>

                <!-- Legal column -->
                <div>
                    <h4 class="wl-footer-col-title">Pháp lý</h4>
                    <ul class="wl-footer-links">
                        <li><a href="#">Bảo mật</a></li>
                        <li><a href="#">Điều khoản</a></li>
                    </ul>
                </div>

            </div>

            <!-- Copyright -->
            <div class="wl-footer-bottom">
                <p class="wl-footer-copy">© {{ date('Y') }} {{ config('app.name', 'Fluid Notes') }} </p>
                <p class="wl-footer-copy">Ngôn ngữ: Tiếng Việt</p>
            </div>

        </div>
    </footer>

    <script>
        // Show desktop nav links on wider viewports
        (function () {
            var links = document.getElementById('nav-links-desktop');
            function check() {
                links.style.display = window.innerWidth >= 768 ? 'flex' : 'none';
            }
            check();
            window.addEventListener('resize', check);
        })();
    </script>

</body>

</html>