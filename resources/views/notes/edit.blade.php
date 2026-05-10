@extends('layouts.app')

@section('title', $note?->title ?: 'Ghi chú mới')

@section('content')

{{-- ═══════════════════════════════════════════════════════
     Note Edit Page  –  Notion-style full-page editor
     All DOM IDs are kept identical to note-modal.blade.php
     so auto-save.js / note-modal.js / note-attachments.js
     / note-slash-menu.js work without any modification.
═══════════════════════════════════════════════════════ --}}

<div class="fnp-page" id="noteEditPage">

    {{-- ── Top bar ──────────────────────────────────────── --}}
    <div class="fnp-topbar">
        {{-- Back breadcrumb --}}
        <a href="{{ route('dashboard') }}" class="fnp-back-btn" title="Quay lại dashboard"
           onclick="event.preventDefault(); var u=sessionStorage.getItem('fn_return_url')||'{{ route('dashboard') }}'; try{sessionStorage.removeItem('fn_return_url');}catch(e){} window.location.href=u;">
            <span class="material-symbols-outlined">arrow_back</span>
            <span class="fnp-back-label">Trang chủ</span>
        </a>

        <div class="fnp-topbar-center">
            <span class="fnp-breadcrumb-sep material-symbols-outlined">chevron_right</span>
            <span class="fnp-breadcrumb-title" id="fnpBreadcrumbTitle">{{ $note?->title ?: ($note ? 'Ghi chú không có tiêu đề' : 'Ghi chú mới') }}</span>
        </div>

        <div class="fnp-topbar-right">
            {{-- Auto-save status indicator (injected by auto-save.js via #modalAutoSaveStatus) --}}
            <span class="fn-autosave-status" id="modalAutoSaveStatus" aria-live="polite"></span>

            {{-- Remote update badge (shown when another user edits this note) --}}
            @if($note)
            <span id="fnpRemoteUpdateBadge"
                  style="font-size:0.72rem; color:var(--fn-primary); opacity:0; transition:opacity 0.4s ease; white-space:nowrap;"></span>
            @endif

            {{-- Presence avatars: other users currently viewing this note --}}
            @if($note)
            <div id="fnpPresenceAvatars" style="display:flex; align-items:center; gap:0;"></div>
            @endif

            @if($isOwner)
                @if($note)
                <button type="button" class="btn btn-sm d-flex align-items-center gap-1 fnp-btn-share" 
                        style="background-color: var(--fn-primary-fixed); color: var(--fn-on-primary-fixed); border: none; border-radius: 8px; padding: 6px 14px; font-weight: 500; transition: background 0.2s;"
                        onmouseover="this.style.backgroundColor='var(--fn-primary-fixed-dim)'" 
                        onmouseout="this.style.backgroundColor='var(--fn-primary-fixed)'"
                        onclick="openShareModal({{ $note->id }}, this)">
                    <span class="material-symbols-outlined" style="font-size: 18px;">share</span>
                    Chia sẻ
                </button>
                {{-- Lock management dropdown (owner only) --}}
                <div class="dropdown">
                    <button type="button" class="btn btn-sm d-flex align-items-center gap-1"
                            style="background:transparent; border: 1px solid var(--fn-outline-variant); border-radius: 8px; padding: 5px 10px; color: var(--fn-on-surface-variant); transition: background 0.2s;"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="{{ $note->is_locked ? 'Quản lý khoá' : 'Khoá ghi chú' }}">
                        <span class="material-symbols-outlined" style="font-size: 18px;">{{ $note->is_locked ? 'lock' : 'lock_open' }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu shadow-sm border-0 rounded-3">
                        @if($note->is_locked)
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                   href="javascript:void(0)"
                                   onclick="openChangeLockModal({{ $note->id }})">
                                    <span class="material-symbols-outlined fn-icon-sm">key</span>
                                    Đổi mật khẩu khoá
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-warning"
                                   href="javascript:void(0)"
                                   onclick="openDisableLockModal({{ $note->id }})">
                                    <span class="material-symbols-outlined fn-icon-sm">no_encryption</span>
                                    Gỡ khoá
                                </a>
                            </li>
                        @else
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                   href="javascript:void(0)"
                                   onclick="openEnableLockModal({{ $note->id }})">
                                    <span class="material-symbols-outlined fn-icon-sm">lock</span>
                                    Khoá bằng mật khẩu
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                @endif
                <span class="fnp-shortcut-hint">
                </span>
            @else
                <span class="fnp-perm-badge {{ $sharePermission }}">
                    {{ $sharePermission === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc' }}
                </span>
            @endif
        </div>
    </div>


    {{-- ── Editor container ─────────────────────────────── --}}
    <div class="fnp-editor-wrap">

        {{-- Thumbnail Preview (above title, same as modal) --}}
        <div class="fn-modal-thumb-preview {{ ($note && $note->attachments->count() > 0) ? '' : 'd-none' }}" id="modalThumbPreview">
            <img src="{{ ($note && $note->attachments->count() > 0) ? $note->attachments->first()->thumbnailUrl(1200) : '' }}"
                 alt="Note thumbnail" id="modalThumbImage" onclick="openLightbox(this.src)">
            @if($isOwner)
                <button type="button" class="fn-modal-thumb-remove" id="modalThumbRemoveBtn" title="Xóa ảnh bìa"
                    onclick="removeModalThumbnail()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            @endif
        </div>

        {{-- Inline image picker (shown when attach btn clicked) --}}
        @if($isOwner)
        <div class="fn-inline-img-picker d-none" id="modalInlineImgPicker">
            <div class="fn-img-picker-zone" id="modalInlinePickerZone">
                <span class="material-symbols-outlined">add_photo_alternate</span>
                <span class="fn-img-picker-label">Nhấn hoặc kéo thả ảnh</span>
                <span class="fn-img-picker-hint">JPEG &bull; PNG &bull; GIF &bull; WebP &bull; tối đa 10 MB</span>
            </div>
            <button type="button" class="fn-picker-cancel-btn" onclick="closeImgPicker()" title="Hủy">
                <span class="material-symbols-outlined">close</span>
            </button>
            <input type="file" id="modalInlinePickerInput"
                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="d-none">
        </div>
        @endif

        {{-- ── Form (same ID as modal so note-modal.js submit logic wires up) ── --}}
        <form action="{{ route('notes.store') }}" method="POST" id="createNoteForm">
            @csrf

            {{-- Top Actions (Notion-style, above title) --}}
            @if($isOwner)
            <div class="fnp-top-actions">
                <button type="button" class="fnp-top-action-btn" id="btnToggleAttachment" title="Thêm ảnh bìa" onclick="toggleAttachmentSection()">
                    <span class="material-symbols-outlined fn-icon-sm">image</span>
                    Thêm ảnh bìa
                </button>
            </div>
            @endif

            {{-- Title row --}}
            <div class="fn-modal-field fn-title-row fnp-title-row">
                <input type="text" name="title" id="modalNoteTitle" class="fn-modal-title-input fnp-title-input"
                    placeholder="Tiêu đề ghi chú"
                    value="{{ $note?->title ?? '' }}"
                    @if(!$isOwner && $sharePermission !== 'edit') readonly @endif
                    required />
            </div>

            {{-- Labels (Inline Notion-style tag pills) --}}
            <div class="fnp-label-pills-section">
                <div id="fnpLabelPills" class="fnp-pill-row"></div>
            </div>

            {{-- Hidden inputs to maintain form state and auto-save --}}
            <div id="modalLabelsChips" class="d-none">
                @foreach($labels as $label)
                    <input type="checkbox" name="label_ids[]" id="modal_label_{{ $label->id }}"
                        value="{{ $label->id }}" class="fn-checkbox-input"
                        {{ ($note && $note->labels->contains($label->id)) ? 'checked' : '' }}
                        @if(!$isOwner && $sharePermission !== 'edit') disabled @endif />
                @endforeach
            </div>

            {{-- Content editor --}}
            <div class="fn-modal-field fn-editor-wrapper fnp-editor-wrapper">
                <div id="modalNoteContent"
                     class="fn-modal-content-input fnp-content-input"
                     contenteditable="{{ ($isOwner || $sharePermission === 'edit') ? 'true' : 'false' }}"
                     data-placeholder="Nhấn '/' để chèn khối • Bắt đầu viết ý tưởng..."></div>
            </div>

            {{-- Image Attachments grid --}}
            @if($isOwner)
            <div class="fn-modal-field fn-attachment-section d-none" id="attachmentSection">
                <label class="fn-modal-labels-title d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fn-icon-sm">image</span>
                    Hình ảnh
                </label>
                <div class="fn-attachment-grid" id="existingAttachments"></div>
                <div class="fn-attachment-grid" id="pendingPreviews"></div>
            </div>
            @endif

        </form>

        {{-- Note meta footer: only shown for existing notes --}}
        @if($note)
        <div class="fnp-meta-footer">
            <span class="fnp-meta-item">
                <span class="material-symbols-outlined fn-icon-sm">schedule</span>
                Cập nhật {{ $note->updated_at->diffForHumans() }}
            </span>
            @if($note->is_pinned)
                <span class="fnp-meta-item">
                    <span class="material-symbols-outlined fn-icon-sm" style="font-variation-settings:'FILL' 1;">star</span>
                    Đã ghim
                </span>
            @endif
            @if($note->is_locked)
                <span class="fnp-meta-item">
                    <span class="material-symbols-outlined fn-icon-sm">lock</span>
                    Đã khoá
                </span>
            @endif
        </div>
        @endif

    </div>{{-- /.fnp-editor-wrap --}}
</div>{{-- /.fnp-page --}}

{{-- Slash Command Menu (body-level) --}}
<div class="fn-slash-menu d-none" id="slashCommandMenu">
    <div class="fn-slash-header">Khối cơ bản</div>
    <div class="fn-slash-list"></div>
    <div class="fn-slash-footer">
        <span class="fn-slash-hint"><kbd>↑↓</kbd> chọn</span>
        <span class="fn-slash-hint"><kbd>↵</kbd> xác nhận</span>
        <span class="fn-slash-hint"><kbd>esc</kbd> đóng</span>
    </div>
</div>

@include('components::note-lock-modals')
@include('components::note-share-modal')

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-page.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-share.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-lock.css') }}">
    {{-- bfcache guard for edit page: reload if restored from bfcache --}}
    <script>
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                window.location.reload();
            }
        });
    </script>
@endpush

@php
    // Pre-compute attachment array (null-safe for create mode where $note is null).
    $fnpAttachments = $note
        ? $note->attachments->map(function ($a) use ($note) {
            return [
                'id'            => $a->id,
                'note_id'       => $note->id,
                'url'           => $a->secure_url,
                'thumbnail_url' => $a->thumbnailUrl(400),
            ];
          })->values()->all()
        : [];
@endphp

@push('scripts')
<script>
    // ── Seed globals expected by note-modal.js / auto-save.js ──────────────────
    window.FN_STORE_URL       = '{{ route("notes.store") }}';
    window.FN_LABEL_STORE_URL = '{{ route("labels.store") }}';

    window.__FNP_EDIT_NOTE_ID = {{ $note?->id ?? 'null' }};
    window.__FNP_NOTE_TITLE   = @json($note?->title ?? '');
    window.__FNP_NOTE_CONTENT = @json($note?->content ?? '');
    window.__FNP_LABEL_IDS    = @json($note ? $note->labels->pluck('id') : []);
    window.__FNP_ATTACHMENTS  = @json($fnpAttachments);
    window.__FNP_IS_OWNER        = {{ $isOwner ? 'true' : 'false' }};
    window.__FNP_LOCKED          = {{ ($note?->is_locked) ? 'true' : 'false' }};
    window.__FNP_SHARE_PERMISSION = @json($sharePermission);  // 'edit'|'view'|null
    window.__FNP_ALL_LABELS      = @json($labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]));
    window.__FNP_CAN_EDIT_LABELS = {{ ($isOwner || $sharePermission === 'edit') ? 'true' : 'false' }};
</script>

<script src="{{ asset('assets/js/notes.js') }}"></script>
<script src="{{ asset('assets/js/note-modal.js') }}"></script>
<script src="{{ asset('assets/js/note-cards.js') }}"></script>
<script src="{{ asset('assets/js/note-img-picker.js') }}"></script>
<script src="{{ asset('assets/js/note-attachments.js') }}"></script>
<script src="{{ asset('assets/js/note-lock.js') }}"></script>
<script src="{{ asset('assets/js/note-share.js') }}"></script>
<script src="{{ asset('assets/js/note-slash-menu.js') }}"></script>
<script src="{{ asset('assets/js/labels.js') }}"></script>
<script src="{{ asset('assets/js/auto-save.js') }}"></script>
<script src="{{ asset('assets/js/note-page.js') }}"></script>
@endpush
