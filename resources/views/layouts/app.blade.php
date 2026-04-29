<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

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

    {{-- Static CSS: Bootstrap + App layout --}}
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app-base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dark-mode.css') }}">

    {{-- Flash-free theme restore: runs before first paint --}}
    <script>
        (function () {
            var t = localStorage.getItem('fn-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
            // Mark icon name on html so the button can read it after render
            document.documentElement.dataset.themeIcon = t === 'dark' ? 'light_mode' : 'dark_mode';
        })();
    </script>

    @stack('styles')
</head>

<body>

    {{-- Sidebar Overlay (mobile) --}}
    <div class="fn-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- Sidebar Navigation --}}
    <aside class="fn-sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="fn-sidebar-brand" style="text-decoration:none;color:inherit;">
            <h1>Fluid Notes</h1>
            <p>Workspace</p>
        </a>

        @auth
            <div class="fn-sidebar-labels">
                <div class="fn-sidebar-labels-header">
                    <h3>NHÃN</h3>
                </div>
                <div class="fn-sidebar-labels-list" id="sidebarLabelsList">
                    @if(isset($sidebarLabels))
                        @foreach($sidebarLabels as $label)
                            <div class="fn-sidebar-label-item" data-label-id="{{ $label->id }}">
                                <div class="fn-sidebar-label-view">
                                    <div class="fn-sidebar-label-info"
                                        onclick="filterNotesByLabel({{ $label->id }}, '{{ addslashes($label->name) }}')"
                                        style="cursor:pointer;">
                                        <span class="material-symbols-outlined">sell</span>
                                        <span class="fn-sidebar-label-name">{{ $label->name }}</span>
                                    </div>
                                    <div class="fn-sidebar-label-actions">
                                        <button onclick="startRenameLabel({{ $label->id }})" title="Đổi tên">
                                            <span class="material-symbols-outlined">edit</span>
                                        </button>
                                        <button onclick="deleteLabel({{ $label->id }})" title="Xóa">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="fn-sidebar-label-edit d-none">
                                    <input type="text" class="fn-sidebar-label-input" value="{{ $label->name }}"
                                        onkeydown="if(event.key==='Enter'){ event.preventDefault(); saveRenameLabel({{ $label->id }}); } else if(event.key==='Escape') { event.preventDefault(); cancelRenameLabel({{ $label->id }}); }"
                                        onblur="setTimeout(() => saveRenameLabel({{ $label->id }}), 150)">
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="fn-sidebar-label-add-wrapper">
                    <button type="button" class="fn-sidebar-label-add-btn" id="sidebarAddBtn"
                        onclick="toggleAddLabelForm()">
                        <span class="material-symbols-outlined">add</span>
                        Thêm mới
                    </button>
                    <div class="fn-sidebar-label-add-form d-none" id="sidebarLabelAddForm">
                        <input type="text" id="newSidebarLabelInput" class="fn-sidebar-label-input"
                            placeholder="Tên nhãn..."
                            onkeydown="if(event.key==='Enter'){ event.preventDefault(); createLabel(); } else if(event.key==='Escape') { event.preventDefault(); toggleAddLabelForm(true); }"
                            onblur="setTimeout(() => onSidebarLabelBlur(), 150)">
                    </div>
                </div>
            </div>
        @endauth

        <div class="fn-sidebar-footer">
            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                @csrf
                <a href="#" class="fn-nav-item"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Đăng xuất</span>
                </a>
            </form>
        </div>

    </aside>

    {{-- Main Content --}}
    <main class="fn-main">
        {{-- Top Navigation Bar --}}
        <header class="fn-header">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <button class="fn-menu-toggle" onclick="toggleSidebar()">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <form action="{{ route('dashboard') }}" method="GET" class="fn-search-box m-0 p-0">
                    <div class="fn-search-box">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" name="q" class="fn-search-input" placeholder="Tìm kiếm ghi chú..."
                            id="globalSearch" value="{{ request('q') }}">
                        @if(request('q'))
                            <a href="{{ route('dashboard') }}" class="fn-search-clear" title="Xóa tìm kiếm">
                                <span class="material-symbols-outlined fn-icon-sm">close</span>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="fn-header-actions">
                <button class="fn-icon-btn" id="darkModeToggle" title="Chuyển chế độ sáng/tối"
                    onclick="toggleDarkMode()">
                    <span class="material-symbols-outlined" id="darkModeIcon">dark_mode</span>
                </button>
                <div class="fn-notification-wrapper" id="notificationWrapper">
                    <button class="fn-icon-btn" title="Thông báo" onclick="toggleNotificationDropdown()">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="fn-notification-dot" id="notificationDot"></span>
                    </button>
                    {{-- Notification Dropdown Panel --}}
                    <div class="fn-notification-dropdown" id="notificationDropdown">
                        <div class="fn-notification-header">
                            <h3>Thông báo <span class="fn-badge" id="notificationBadge" style="display:none;">0</span>
                            </h3>
                            <button class="fn-notification-clear-btn" onclick="markAllAsRead()">Đánh dấu đã đọc</button>
                        </div>
                        <div class="fn-notification-list" id="notificationList">
                            <div class="fn-notification-empty">
                                <span class="material-symbols-outlined">notifications_off</span>
                                <p>Không có thông báo nào</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fn-header-divider"></div>
                <button class="fn-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    @if(Auth::user()->avatarUrl())
                        <img src="{{ Auth::user()->avatarUrl() }}" alt="Avatar" class="fn-user-avatar">
                    @else
                        <div class="fn-user-avatar fn-user-avatar-initial">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    @endif
                    <span class="fn-user-name d-none d-sm-inline">{{ Auth::user()->name }}</span>
                    <span class="material-symbols-outlined fn-icon-sm fn-user-chevron">expand_more</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 fn-dropdown-menu">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('profile') }}">
                            <span class="material-symbols-outlined fn-icon-sm">person</span>
                            Hồ sơ
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="#"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <span class="material-symbols-outlined fn-icon-sm">logout</span>
                            Đăng xuất
                        </a>
                    </li>
                </ul>
            </div>
        </header>

        {{-- Page Content --}}
        @yield('content')
    </main>

    {{-- FAB (Mobile) --}}
    <button class="fn-fab" onclick="openNewNoteModal()">
        <span class="material-symbols-outlined">add</span>
    </button>

    {{-- Static JS: Bootstrap + Core helpers --}}
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>

    {{-- Toast Container --}}
    <div class="fn-toast-container" id="toastContainer"></div>

    @auth
        {{-- Pusher & Laravel Echo (CDN) --}}
        <script src="https://js.pusher.com/8.4/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js"></script>

        {{-- Pass config to JS --}}
        <script>
            window.__userId = {{ Auth::id() }};
            window.__pusherKey = '{{ config("broadcasting.connections.pusher.key") }}';
            window.__pusherCluster = '{{ config("broadcasting.connections.pusher.options.cluster") }}';
            window.__appUrl = '{{ rtrim(config("app.url"), "/") }}';
            window.__appDebug = {{ config('app.debug') ? 'true' : 'false' }};
        </script>
        <script src="{{ asset('assets/js/echo-init.js') }}"></script>
    @endauth

    @stack('scripts')
</body>

</html>