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

    {{-- App Styles (split for maintainability) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app-base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/header.css') }}">

    @stack('styles')
</head>

<body>

    {{-- Sidebar Overlay (mobile) --}}
    <div class="fn-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- Sidebar Navigation --}}
    <aside class="fn-sidebar" id="sidebar">
        <div class="fn-sidebar-brand">
            <h1>Fluid Notes</h1>
            <p>Workspace</p>
        </div>

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
                                    <div class="fn-sidebar-label-info">
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
            <a href="#" class="fn-nav-item">
                <span class="material-symbols-outlined">help</span>
                <span>Trợ giúp</span>
            </a>
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
                <button class="fn-icon-btn" title="Chế độ tối">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
                <button class="fn-icon-btn" title="Thông báo">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="fn-notification-dot"></span>
                </button>
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
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#">
                            <span class="material-symbols-outlined fn-icon-sm">settings</span>
                            Cài đặt
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

    {{-- Bootstrap JS --}}
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

    {{-- App JS (sidebar, toast, helpers) --}}
    <script src="{{ asset('assets/js/app.js') }}"></script>

    {{-- Toast Container --}}
    <div class="fn-toast-container" id="toastContainer"></div>

    @stack('scripts')
</body>

</html>