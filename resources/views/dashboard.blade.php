@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="fn-dashboard">
        <div class="row g-4">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="fn-section-title">
                            @if(isset($isSharedView) && $isSharedView)
                                Ghi chú được chia sẻ
                            @elseif(isset($searchQuery) && $searchQuery)
                                Kết quả tìm kiếm cho "{{ $searchQuery }}"
                            @else
                                Ghi chú gần đây
                            @endif
                        </h4>
                        <div class="fn-view-toggle">
                            <button type="button" class="fn-toggle-btn active" id="btnGridView"
                                onclick="setNotesView('grid')" title="Dạng lưới">
                                <span class="material-symbols-outlined fn-icon-sm">grid_view</span>
                            </button>
                            <button type="button" class="fn-toggle-btn" id="btnListView"
                                onclick="setNotesView('list')" title="Dạng danh sách">
                                <span class="material-symbols-outlined fn-icon-sm">view_list</span>
                            </button>
                        </div>
                    </div>

                    {{-- Action buttons: right-aligned, only for own workspace --}}
                    @if(!(isset($isSharedView) && $isSharedView) && !isset($workspaceShare) && isset($activeWorkspace))
                        <div class="d-flex align-items-center gap-2">
                            {{-- Share workspace button (idea from note edit page) --}}
                            <button type="button"
                                    class="btn btn-sm d-flex align-items-center gap-1 fn-ws-share-btn"
                                    onclick="openWsShareModal({{ $activeWorkspace->id }}, '{{ addslashes($activeWorkspace->is_default ? Auth::user()->name . "'s Space" : $activeWorkspace->name) }}', this)"
                                    title="Chia sẻ workspace">
                                <span class="material-symbols-outlined" style="font-size: 18px;">share</span>
                                Chia sẻ
                            </button>

                            {{-- Settings button --}}
                            <button type="button" class="fn-ws-page-settings-btn"
                                    onclick="openWorkspaceSettings({{ $activeWorkspace->id }})"
                                    title="Cài đặt workspace">
                                <span class="material-symbols-outlined">settings</span>
                                <span class="fn-ws-page-settings-label">Cài đặt</span>
                            </button>
                        </div>
                    @endif

                </div>


                {{-- Notes Grid / List --}}
                @if(isset($isSharedView) && $isSharedView)
                    {{-- ── Shared-with-me view ── --}}
                    @if($sharedNotes->count() > 0)
                        <div class="row g-3" id="notesContainer">
                            @foreach($sharedNotes as $share)
                                @php $note = $share->note; @endphp
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-animate-in fn-shared-note-col"
                                     data-note-id="{{ $note->id }}"
                                     data-share-id="{{ $share->id }}"
                                     data-permission="{{ $share->permission }}"
                                     data-locked="{{ $note->is_locked ? '1' : '0' }}">
                                    <div class="fn-note-card fn-shared-card"
                                         onclick="openSharedNoteOrUnlock({{ $note->id }}, '{{ $share->permission }}', {{ $note->is_locked ? 'true' : 'false' }})"
                                         style="cursor:pointer;">
                                        @if($note->attachments->count() > 0)
                                            <img class="fn-note-thumb"
                                                src="{{ $note->attachments->first()->thumbnailUrl(400) }}"
                                                alt="Note image" loading="lazy" decoding="async">
                                        @endif
                                        <div class="fn-shared-owner">
                                            <div class="fn-shared-owner-avatar">
                                                @if($note->user->avatarUrl())
                                                    <img src="{{ $note->user->avatarUrl() }}" alt="{{ $note->user->name }}">
                                                @else
                                                    {{ strtoupper(substr($note->user->name, 0, 2)) }}
                                                @endif
                                            </div>
                                            <span class="fn-shared-owner-name">{{ $note->user->name }}</span>
                                            <span class="fn-perm-badge {{ $share->permission }}">{{ $share->permission === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc' }}</span>
                                        </div>
                                        <div class="fn-note-card-header">
                                            <h4 class="fn-note-title">{{ $note->title }}</h4>
                                        </div>
                                        @if($note->labels->count() > 0)
                                            <div class="fn-note-labels">
                                                @foreach($note->labels->take(3) as $label)
                                                    <span class="badge rounded-pill fn-label-badge">{{ $label->name }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        <p class="fn-note-excerpt">{{ Str::limit(strip_tags($note->content ?? ''), 120) }}</p>
                                        <div class="fn-note-meta">
                                            <span class="fn-note-date">{{ $note->updated_at->diffForHumans() }}</span>
                                            <span class="material-symbols-outlined fn-share-badge" title="Được chia sẻ">group</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5 text-muted fn-empty-state">
                            <span class="material-symbols-outlined d-block mb-3">group</span>
                            <p class="small opacity-75">Chưa có ghi chú nào được chia sẻ với bạn.</p>
                        </div>
                    @endif
                @else
                    {{-- Normal notes view: always show + card first, then notes --}}
                    <div class="row g-3" id="notesContainer">

                        {{-- ── Create Note Card (always first) ── --}}
                        @if(isset($canCreateNote) && $canCreateNote)
                            <div class="col-12 col-md-6 col-lg-4 col-xl-3" id="createNoteCard">
                                <a href="{{ route('notes.create') }}" class="fn-new-note-card" title="Tạo ghi chú mới">
                                    <span class="fn-new-note-icon material-symbols-outlined">add</span>
                                </a>
                            </div>
                        @endif

                        @foreach($recentNotes as $note)
                <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-animate-in note-col"
                                data-note-id="{{ $note->id }}"
                                @if($note->is_pinned) data-pinned="1" @endif
                                @if($note->is_locked) data-locked="1" @endif>
                                <div class="fn-note-card">

                                    {{-- Thumbnail --}}
                                    @if($note->attachments->count() > 0)
                                        <img class="fn-note-thumb"
                                            src="{{ $note->attachments->first()->thumbnailUrl(400) }}"
                                            alt="Note image"
                                            loading="lazy"
                                            decoding="async">
                                    @endif

                                    {{-- Dropdown Menu --}}
                                    <div class="dropdown fn-note-dropdown">
                                        <span class="material-symbols-outlined fn-note-more"
                                            data-bs-toggle="dropdown" aria-expanded="false">more_vert</span>
                                        <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu shadow-sm border-0 rounded-3">
                                            {{-- Pin / Unpin --}}
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                    href="javascript:void(0)"
                                                    onclick="togglePinAjax({{ $note->id }}, {{ $note->is_pinned ? 'true' : 'false' }})">
                                                    <span class="material-symbols-outlined fn-icon-sm"
                                                        @if($note->is_pinned) style="font-variation-settings:'FILL' 1;" @endif>push_pin</span>
                                                    {{ $note->is_pinned ? 'Bỏ ghim' : 'Ghim' }}
                                                </a>
                                            </li>

                                            <li class="fn-lock-divider"><hr class="dropdown-divider"></li>

                                            {{-- Lock management --}}
                                            @if($note->is_locked)
                                                <li class="fn-lock-menu-item">
                                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                        href="javascript:void(0)"
                                                        onclick="openChangeLockModal({{ $note->id }})">
                                                        <span class="material-symbols-outlined fn-icon-sm">key</span>
                                                        Đổi mật khẩu khoá
                                                    </a>
                                                </li>
                                                <li class="fn-lock-menu-item">
                                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-warning"
                                                        href="javascript:void(0)"
                                                        onclick="openDisableLockModal({{ $note->id }})">
                                                        <span class="material-symbols-outlined fn-icon-sm">no_encryption</span>
                                                        Gỡ khoá
                                                    </a>
                                                </li>
                                            @else
                                                <li class="fn-lock-menu-item">
                                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                        href="javascript:void(0)"
                                                        onclick="openEnableLockModal({{ $note->id }})">
                                                        <span class="material-symbols-outlined fn-icon-sm">lock</span>
                                                        Khoá
                                                    </a>
                                                </li>
                                            @endif

                                            <li><hr class="dropdown-divider"></li>

                                            {{-- Share --}}
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                    href="javascript:void(0)"
                                                    onclick="openShareModal({{ $note->id }})">
                                                    <span class="material-symbols-outlined fn-icon-sm">share</span>
                                                    Chia sẻ
                                                </a>
                                            </li>

                                            <li><hr class="dropdown-divider"></li>

                                            {{-- Delete --}}
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger"
                                                    href="javascript:void(0)"
                                                    onclick="deleteNoteAjax({{ $note->id }})">
                                                    <span class="material-symbols-outlined fn-icon-sm">delete</span>
                                                    Xóa
                                                </a>
                                            </li>
                                        </ul>
                                    </div>

                                    {{-- Title --}}
                                    <div class="fn-note-card-header">
                                        <h4 class="fn-note-title">{{ $note->title }}</h4>
                                    </div>

                                    {{-- Labels --}}
                                    @if($note->labels->count() > 0)
                                        <div class="fn-note-labels">
                                            @foreach($note->labels->take(3) as $label)
                                                <span class="badge rounded-pill fn-label-badge"
                                                    data-label-id="{{ $label->id }}">{{ $label->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Excerpt --}}
                                    <p class="fn-note-excerpt">{{ Str::limit(strip_tags(preg_replace('/<div[^>]*class="fn-content-image-block"[^>]*>[\s\S]*?<\/div>|<div[^>]*class="fn-content-divider"[^>]*>[\s\S]*?<\/div>/i', '', $note->content ?? '')), 120) }}</p>

                                    {{-- Meta --}}
                                    <div class="fn-note-meta">
                                        <span class="fn-note-date">{{ $note->updated_at->diffForHumans() }}</span>
                                        <div class="d-flex align-items-center gap-1">
                                            @if($note->is_pinned)
                                                 <span class="material-symbols-outlined fn-icon-sm fn-pin-badge" style="font-variation-settings:'FILL' 1;">push_pin</span>
                                            @endif
                                            @if($note->is_locked)
                                                <span class="material-symbols-outlined fn-lock-badge"
                                                    title="Ghi chú đã khoá">lock</span>
                                            @endif
                                            @if($note->shares->count() > 0)
                                                <span class="material-symbols-outlined fn-share-badge"
                                                    title="Đang chia sẻ">group</span>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Empty state for search (no + card needed when no results) --}}
                @if(isset($searchQuery) && $searchQuery && !isset($isSharedView) && $recentNotes->count() === 0)
                    <div class="text-center py-5 text-muted fn-empty-state">
                        <span class="material-symbols-outlined d-block mb-3">search_off</span>
                        <p class="small opacity-75">Không tìm thấy kết quả cho "{{ $searchQuery }}".</p>
                    </div>
                @endif

                {{-- Load More Button --}}
                @if(!isset($searchQuery) || !$searchQuery)
                    <div class="text-center mt-4" id="loadMoreWrapper"
                         style="{{ $hasMoreNotes ? '' : 'display:none;' }}">
                        <button type="button" class="fn-load-more-btn" id="loadMoreBtn"
                                onclick="loadMoreNotes()">
                            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">expand_more</span>
                            Xem thêm ghi chú
                        </button>
                    </div>
                    <div class="text-center mt-3" id="loadMoreSpinner" style="display:none;">
                        <div class="fn-spinner"></div>
                    </div>
                @endif

            </div>
        </div>


</div>{{-- end fn-dashboard --}}

    @include('components::note-lock-modals')
    @include('components::note-share-modal')

    {{-- ═══ Shared Note View/Edit Modal ═══ --}}
    <div id="sharedNoteModal" class="fn-modal-overlay"
         onclick="if(event.target===this) closeSharedNoteModal()">
        <div class="fn-modal-card">

            {{-- Header --}}
            <div class="fn-modal-header">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="fn-modal-icon">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div>
                        <h2 class="fn-modal-title">Ghi chú được chia sẻ</h2>
                        <small class="sn-owner-badge" style="font-size:12px; color:var(--fn-on-surface-variant);"></small>
                    </div>
                    <span class="fn-perm-badge sn-perm-badge ms-1"></span>
                </div>
                {{-- Presence avatars: people currently viewing this note --}}
                <div id="snPresenceAvatars" class="sn-presence-avatars" title="Đang xem ghi chú này"></div>
                <button type="button" class="fn-modal-close" onclick="closeSharedNoteModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Body --}}
            <div class="fn-modal-body">
                <input type="text" class="sn-title fn-modal-title-input" placeholder="Tiêu đề...">
                <div class="sn-content fn-modal-content-input"
                     contenteditable="false"
                     data-placeholder="Nội dung ghi chú..."
                     style="margin-top:1rem; min-height:160px;"></div>
            </div>

            {{-- Footer --}}
            <div class="fn-modal-footer">
                <div class="fn-modal-toolbar">{{-- placeholder --}}</div>
                <div class="fn-modal-actions">
                    <button type="button" class="fn-modal-btn-cancel" onclick="closeSharedNoteModal()">Đóng</button>
                    <button type="button" class="fn-modal-btn-save sn-save-btn" onclick="saveSharedNote()">
                        <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">save</span>
                        Lưu
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- ═══ Slash Command Menu (body-level, shared by all editors) ═══ --}}
    <div class="fn-slash-menu d-none" id="slashCommandMenu">
        <div class="fn-slash-header">Khối cơ bản</div>
        <div class="fn-slash-list"></div>
        <div class="fn-slash-footer">
            <span class="fn-slash-hint"><kbd>↑↓</kbd> chọn</span>
            <span class="fn-slash-hint"><kbd>↵</kbd> xác nhận</span>
            <span class="fn-slash-hint"><kbd>esc</kbd> đóng</span>
        </div>
    </div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-lock.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-share.css') }}">
@endpush

@push('scripts')
    <script>
        window.FN_LABEL_STORE_URL = '{{ route("labels.store") }}';
        window.FN_SHARED_CARDS_URL = '{{ route("notes.shared.cards") }}';
        window.FN_SHARED_VIEW_BASE = '/notes';
        window.FN_FILTER_LABEL_URL = '{{ route("dashboard.filter.label") }}';
        window.FN_LOAD_MORE_URL = '{{ route("dashboard.load.more") }}';
        window.FN_NEXT_CURSOR = @json($nextCursor);
        window.FN_HAS_MORE = @json($hasMoreNotes);
        window.__activeWorkspaceId = @json($activeWorkspace?->id ?? null);
        window.__canCreateNote = @json($canCreateNote ?? false);

        // bfcache guard + dedup (safety net for fresh loads)
        (function () {
            function _deduplicateNoteCards() {
                var seen = {};
                document.querySelectorAll('#notesContainer .note-col[data-note-id]').forEach(function (col) {
                    var id = col.dataset.noteId;
                    if (seen[id]) { col.remove(); } else { seen[id] = true; }
                });
            }
            // Run on DOMContentLoaded for fresh loads
            document.addEventListener('DOMContentLoaded', _deduplicateNoteCards);
            // Run on pageshow for bfcache restores (e.persisted = true)
            // Bfcache preserves existing event listeners so this WILL fire correctly
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    window.location.reload();
                } else {
                    _deduplicateNoteCards();
                }
            });
        })();
    </script>
    <script src="{{ asset('assets/js/notes.js') }}"></script>
    <script src="{{ asset('assets/js/note-cards.js') }}"></script>
    <script src="{{ asset('assets/js/note-lock.js') }}"></script>
    <script src="{{ asset('assets/js/note-share.js') }}"></script>
    <script src="{{ asset('assets/js/labels.js') }}"></script>
    <script src="{{ asset('assets/js/shared-notes.js') }}"></script>
    <script src="{{ asset('assets/js/live-search.js') }}"></script>

    {{-- ── Workspace Lock Gate ─────────────────────────────────────── --}}
    @if(isset($requiresPasswordVerify) && $requiresPasswordVerify && isset($activeWorkspace))
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-open verify modal — user must enter password before seeing notes
        var wsId   = {{ $activeWorkspace->id }};
        var wsName = @json($activeWorkspace->is_default ? Auth::user()->name . "'s Space" : $activeWorkspace->name);

        // Hide note content area until verified
        var noteSection = document.querySelector('.fn-notes-section');
        if (noteSection) noteSection.style.visibility = 'hidden';

        openWsVerifyModal(wsId, wsName, function () {
            // After verify, reload page — DashboardController will read the flash key
            window.location.href = '/dashboard';
        });

        // Override close button so user can't dismiss without verifying
        window.closeWsVerifyModal = function () {
            // Do nothing — must verify to access this workspace
        };
    });
    </script>
    @endif

@endpush