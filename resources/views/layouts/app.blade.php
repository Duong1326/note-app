<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Fluid Notes') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">

    {{-- Bootstrap 5 --}}
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">

    {{-- App Styles --}}
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">

    @stack('styles')
</head>

<body>

    {{-- Sidebar Overlay (mobile) --}}
    <div class="fn-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- ═══ Sidebar Navigation ═══ --}}
    <aside class="fn-sidebar" id="sidebar">
        <div class="fn-sidebar-brand">
            <h1>Fluid Notes</h1>
            <p>Workspace</p>
        </div>

        <nav class="fn-nav">
            <a href="{{ route('dashboard') }}"
                class="fn-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-symbols-outlined">home</span>
                <span>Home</span>
            </a>
            <a href="{{ route('notes.index') }}"
                class="fn-nav-item {{ request()->routeIs('notes.*') ? 'active' : '' }}">
                <span class="material-symbols-outlined">description</span>
                <span>All Notes</span>
            </a>
            <a href="#" class="fn-nav-item">
                <span class="material-symbols-outlined">star</span>
                <span>Favorites</span>
            </a>
            <a href="#" class="fn-nav-item">
                <span class="material-symbols-outlined">history</span>
                <span>Recently Edited</span>
            </a>
        </nav>

        <div class="fn-sidebar-footer">
            <a href="#" class="fn-nav-item">
                <span class="material-symbols-outlined">help</span>
                <span>Help</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                @csrf
                <a href="#" class="fn-nav-item"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Logout</span>
                </a>
            </form>
        </div>

    </aside>

    {{-- ═══ Main Content ═══ --}}
    <main class="fn-main">
        {{-- Top Navigation Bar --}}
        <header class="fn-header">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <button class="fn-menu-toggle" onclick="toggleSidebar()">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div class="fn-search-box">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" class="fn-search-input" placeholder="Search your notes..." id="globalSearch">
                </div>
            </div>

            <div class="fn-header-actions">
                <button class="fn-icon-btn" title="Dark mode">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
                <button class="fn-icon-btn" title="Notifications">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="fn-notification-dot"></span>
                </button>
                <div class="fn-header-divider"></div>
                <button class="fn-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="fn-user-avatar d-flex align-items-center justify-content-center"
                        style="background: var(--fn-primary-container); color: var(--fn-on-primary); font-weight: 700; font-size: 0.8rem;">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <span class="fn-user-name d-none d-sm-inline">{{ Auth::user()->name }}</span>
                    <span class="material-symbols-outlined"
                        style="font-size: 18px; color: var(--fn-on-surface-variant);">expand_more</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 0.75rem;">
                    <li>
                        <a class="dropdown-item py-2 px-3" href="#">
                            <span class="material-symbols-outlined me-2"
                                style="font-size: 18px; vertical-align: middle;">person</span>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 px-3" href="#">
                            <span class="material-symbols-outlined me-2"
                                style="font-size: 18px; vertical-align: middle;">settings</span>
                            Settings
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item py-2 px-3 text-danger" href="#"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <span class="material-symbols-outlined me-2"
                                style="font-size: 18px; vertical-align: middle;">logout</span>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </header>

        {{-- Page Content --}}
        @yield('content')
    </main>

    {{-- FAB (Mobile) --}}
    <button class="fn-fab" onclick="window.location.href='#'">
        <span class="material-symbols-outlined">add</span>
    </button>

    {{-- Bootstrap JS --}}
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

    {{-- Sidebar Toggle --}}
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        }

        // Close sidebar on resize to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
        });
    </script>

    @stack('scripts')
</body>

</html>