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
    <link rel="stylesheet" href="{{ asset('assets/css/workspace.css') }}">
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

        @auth
            {{-- Fluid Notes Branding --}}
            <div class="fn-sidebar-brand" style="pointer-events:none; display: flex; align-items: center; gap: 8px;">
                <img src="{{ asset('logo.png') }}" alt="Logo" style="height: 32px; width: auto;">
                <h1 style="margin-bottom: 0;">Fluid Notes</h1>
            </div>

            {{-- Workspace Header --}}
            @php
                $__activeWsInHdr = null;
                if (isset($sidebarWorkspaces)) {
                    $__activeWsInHdr = $sidebarWorkspaces->firstWhere('id', $activeWorkspaceId);
                    if (!$__activeWsInHdr && isset($sidebarSharedWorkspaces)) {
                        $__shWs = $sidebarSharedWorkspaces->firstWhere('workspace_id', $activeWorkspaceId);
                        $__activeWsInHdr = $__shWs?->workspace;
                    }
                }
                $__wsDispName = match (true) {
                    request()->get('view') === 'shared' => 'Chia sẻ với tôi',
                    $__activeWsInHdr && $__activeWsInHdr->is_default => Auth::user()->name . "'s Space",
                    $__activeWsInHdr !== null => $__activeWsInHdr->name,
                    default => Auth::user()->name . "'s Space",
                };
                $__wsInit = strtoupper(substr($__wsDispName, 0, 1));
            @endphp

            <div class="fn-ws-header" onclick="toggleWorkspaceDropdown()">
                <span class="fn-ws-header-name" id="wsActiveName">{{ $__wsDispName }}</span>
                <span class="material-symbols-outlined fn-ws-header-chevron" id="wsChevron">unfold_more</span>
            </div>

            <div class="fn-ws-switcher" id="workspaceSwitcher">
                <div class="fn-ws-dropdown d-none" id="wsDropdown">

                    {{-- Info block --}}
                    <div class="fn-ws-dropdown-info">
                        <div class="fn-ws-dropdown-info-body">
                            <div class="fn-ws-dropdown-info-name">{{ $__wsDispName }}</div>
                            <div class="fn-ws-dropdown-info-meta">Free Plan</div>
                        </div>
                    </div>

                    {{-- Pill buttons (only for owned non-default) --}}
                    @php
                        $__ownedActive = isset($sidebarWorkspaces) ? $sidebarWorkspaces->firstWhere('id', $activeWorkspaceId) : null;
                    @endphp
                    @if($__ownedActive && !$__ownedActive->is_default)
                        <div class="fn-ws-pill-row">
                            <button class="fn-ws-pill-btn" onclick="openWorkspaceSettings({{ $__ownedActive->id }})">
                                <span class="material-symbols-outlined">settings</span>
                                Settings
                            </button>
                        </div>
                    @endif

                    <hr class="fn-ws-sep">

                    {{-- Account row --}}
                    <div class="fn-ws-account-row">
                        <span class="fn-ws-account-email">{{ Auth::user()->email }}</span>
                    </div>

                    {{-- Default (personal) workspace --}}
                    @if(isset($sidebarWorkspaces))
                        @php $__defWs = $sidebarWorkspaces->firstWhere('is_default', true); @endphp
                        @if($__defWs)
                            <div class="fn-ws-item {{ ($__defWs->id == $activeWorkspaceId && request()->get('view') !== 'shared') ? 'active' : '' }}"
                                data-ws-id="{{ $__defWs->id }}" data-ws-name="{{ Auth::user()->name }}'s Space" data-ws-default="1"
                                data-ws-locked="{{ $__defWs->is_locked ? '1' : '0' }}">
                                <div class="fn-ws-item-info"
                                    onclick="switchWorkspace({{ $__defWs->id }}, '{{ addslashes(Auth::user()->name) }}\'s Space')">
                                    <span class="fn-ws-item-name">{{ Auth::user()->name }}'s Space</span>
                                </div>
                                @if($__defWs->id == $activeWorkspaceId && request()->get('view') !== 'shared')
                                    <span class="material-symbols-outlined fn-ws-item-check">check</span>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Other owned workspaces --}}
                    <div id="wsOwnedList">
                        @if(isset($sidebarWorkspaces))
                            @foreach($sidebarWorkspaces as $ws)
                                @if($ws->is_default) @continue @endif
                                <div class="fn-ws-item {{ ($ws->id == $activeWorkspaceId && request()->get('view') !== 'shared') ? 'active' : '' }}"
                                    data-ws-id="{{ $ws->id }}" data-ws-name="{{ $ws->name }}" data-ws-default="0"
                                    data-ws-locked="{{ $ws->is_locked ? '1' : '0' }}">
                                    <div class="fn-ws-item-info"
                                        onclick="switchWorkspace({{ $ws->id }}, '{{ addslashes($ws->name) }}')">
                                        <span class="fn-ws-item-name">{{ $ws->name }}</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:2px;">
                                        @if($ws->id == $activeWorkspaceId && request()->get('view') !== 'shared')
                                            <span class="material-symbols-outlined fn-ws-item-check">check</span>
                                        @endif
                                        <div class="fn-ws-item-actions">
                                            <button onclick="openWorkspaceSettings({{ $ws->id }})" title="Settings">
                                                <span class="material-symbols-outlined">more_horiz</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- Shared workspaces --}}
                    @if(isset($sidebarSharedWorkspaces) && $sidebarSharedWorkspaces->count() > 0)
                        <hr class="fn-ws-sep" style="margin:0.25rem 0;">
                        <div id="wsSharedList">
                            @foreach($sidebarSharedWorkspaces as $share)
                                @php $sws = $share->workspace; @endphp
                                <div class="fn-ws-item fn-ws-shared {{ $sws->id == $activeWorkspaceId ? 'active' : '' }}"
                                    data-ws-id="{{ $sws->id }}" data-ws-name="{{ $sws->name }}"
                                    data-ws-locked="{{ $sws->is_locked ? '1' : '0' }}">
                                    <div class="fn-ws-item-info"
                                        onclick="switchWorkspace({{ $sws->id }}, '{{ addslashes($sws->name) }}')">
                                        <span class="fn-ws-item-name">{{ $sws->name }}</span>
                                        <span class="fn-ws-perm-badge">{{ $share->permission === 'edit' ? 'Sửa' : 'Đọc' }}</span>
                                    </div>
                                    @if($sws->id == $activeWorkspaceId)
                                        <span class="material-symbols-outlined fn-ws-item-check">check</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Shared-with-me --}}
                    <div class="fn-ws-item fn-ws-shared-me-item {{ request()->get('view') === 'shared' ? 'active' : '' }}"
                        onclick="switchToSharedView()">
                        <div class="fn-ws-item-info">
                            <span class="material-symbols-outlined fn-ws-item-icon">group</span>
                            <span class="fn-ws-item-name">Chia sẻ với tôi</span>
                        </div>
                        @if(request()->get('view') === 'shared')
                            <span class="material-symbols-outlined fn-ws-item-check">check</span>
                        @endif
                    </div>

                    {{-- New workspace --}}
                    <hr class="fn-ws-sep" style="margin:0.25rem 0;">
                    <button class="fn-ws-new-btn" onclick="openCreateWorkspaceModal()">
                        <span class="material-symbols-outlined">add</span>
                        New workspace
                    </button>

                </div>
            </div>
        @endauth

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
                {{-- Live search: no form submit, AJAX debounce 300ms --}}
                <div class="fn-search-box">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" class="fn-search-input" placeholder="Tìm kiếm ghi chú..." id="globalSearch"
                        autocomplete="off" value="{{ request('q') }}">
                    <button type="button" class="fn-search-clear" id="searchClearBtn"
                        style="display:{{ request('q') ? 'flex' : 'none' }};" title="Xóa tìm kiếm"
                        onclick="clearLiveSearch()">
                        <span class="material-symbols-outlined fn-icon-sm">close</span>
                    </button>
                    <span class="fn-search-spinner" id="searchSpinner" style="display:none;"></span>
                </div>
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
    @auth
        @if(isset($layoutCanCreateNote) && $layoutCanCreateNote && request()->get('view') !== 'shared')
            <a href="{{ route('notes.create') }}" class="fn-fab" title="Ghi chú mới">
                <span class="material-symbols-outlined">add</span>
            </a>
        @elseif(!isset($layoutCanCreateNote) || !$layoutCanCreateNote)
            {{-- Locked workspace: FAB shown but disabled --}}
            <span class="fn-fab fn-fab-disabled" title="Bạn không có quyền tạo ghi chú trong workspace này"
                style="opacity:0.4; cursor:not-allowed; pointer-events:none;">
                <span class="material-symbols-outlined">add</span>
            </span>
        @endif
    @endauth

    {{-- Static JS: Bootstrap + Core helpers --}}
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="{{ asset('assets/js/workspace.js') }}"></script>

    {{-- Workspace Modals --}}
    @auth
        @include('components::workspace-modals')
    @endauth

    {{-- Toast Container --}}
    <div class="fn-toast-container" id="toastContainer"></div>

    {{-- Global Image Lightbox (used by openLightbox() in note-attachments.js) --}}
    <div class="fn-lightbox-overlay d-none" id="imageLightbox" onclick="closeLightbox(event)">
        <button type="button" class="fn-lightbox-close" onclick="closeLightbox()" title="Đóng">
            <span class="material-symbols-outlined">close</span>
        </button>
        <img src="" alt="Preview" id="lightboxImage" class="fn-lightbox-img" onclick="event.stopPropagation()">
    </div>

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
            window.FN_SEARCH_URL = '{{ route("dashboard.search") }}';
            // Active workspace for real-time lock enforcement
            window.__activeWorkspaceId = {{ session('active_workspace_id', 'null') }};
        </script>
        <script src="{{ asset('assets/js/echo-init.js') }}"></script>

        {{-- ── Global WorkspaceLocked real-time listener ───────────────
             Works on EVERY page. When the owner locks the active workspace,
             all members (on any page) immediately see a password prompt.
             NOTE: We register a hook that echo-init.js will call after
             EchoInstance is created, to avoid the race condition where this
             script runs before Echo is initialised.
        ─────────────────────────────────────────────────────────────── --}}
        <script>
        // Register a post-init hook that echo-init.js calls after creating EchoInstance
        window.__echoPostInitHooks = window.__echoPostInitHooks || [];
        window.__echoPostInitHooks.push(function (echoInstance) {
            var activeWsId = window.__activeWorkspaceId;
            if (!activeWsId) return;

            echoInstance.private('user.' + window.__userId)
                .listen('.workspace.locked', function (data) {
                    var lockedWsId = parseInt(data.workspace_id);

                    // Only react if the locked workspace is the one the user is in
                    if (lockedWsId !== parseInt(activeWsId)) return;

                    // Clear cached sessionStorage unlock token
                    try { sessionStorage.removeItem('fn_ws_token_' + lockedWsId); } catch (e) {}

                    // Hide page content to prevent seeing locked notes
                    var mainContent = document.querySelector('.fn-main-content, .fn-dashboard-content, main');
                    if (mainContent) mainContent.style.visibility = 'hidden';

                    // Toast notification
                    if (typeof showToast === 'function') {
                        showToast('Workspace vừa được khoá. Vui lòng nhập mật khẩu để tiếp tục.', 'warning');
                    }

                    // Show verify modal immediately (blocks UI)
                    var wsName = data.workspace_name || 'Workspace';
                    if (typeof openWsVerifyModal === 'function') {
                        openWsVerifyModal(lockedWsId, wsName, function () {
                            window.location.href = '/dashboard';
                        });
                        // Prevent dismissing without verifying
                        window.closeWsVerifyModal = function () {};
                    } else {
                        window.location.href = '/dashboard';
                    }
                });
        });
        </script>
    @endauth

    @stack('scripts')
</body>

</html>