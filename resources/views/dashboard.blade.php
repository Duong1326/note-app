@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="fn-dashboard">

        {{-- Welcome Section --}}
        <section class="fn-welcome fn-animate-in">
            <div class="row align-items-end justify-content-between g-4">
                <div class="col-12 col-md-8">
                    <h2 class="fn-welcome-title">Chào mừng trở lại, {{ Auth::user()->name }}</h2>
                    <p class="fn-welcome-sub">Sẵn sàng ghi lại ý tưởng của bạn?</p>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <button type="button" class="fn-btn-new-note" onclick="openNewNoteModal()">
                        <span class="material-symbols-outlined">add</span>
                        Ghi chú mới
                    </button>
                </div>
            </div>
        </section>

        {{-- Recent Notes --}}
        <div class="row g-4">
            <div class="col-12">

                {{-- Section Header --}}
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <h3 class="fn-section-title">
                            @if(isset($searchQuery) && $searchQuery)
                                Kết quả tìm kiếm cho "{{ $searchQuery }}"
                            @else
                                Ghi chú gần đây
                            @endif
                        </h3>
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
                </div>

                {{-- Notes Grid / List --}}
                @if($recentNotes->count() > 0)
                    <div class="row g-3" id="notesContainer">
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
                                            alt="Note image">
                                    @endif

                                    {{-- Dropdown Menu --}}
                                    <div class="dropdown fn-note-dropdown">
                                        <span class="material-symbols-outlined fn-note-more"
                                            data-bs-toggle="dropdown" aria-expanded="false">more_vert</span>
                                        <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu shadow-sm border-0 rounded-3">
                                            {{-- Edit --}}
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                    href="javascript:void(0)"
                                                    data-id="{{ $note->id }}"
                                                    data-title="{{ $note->title }}"
                                                    data-content="{{ $note->content }}"
                                                    data-labels="{{ $note->labels->pluck('id')->toJson() }}"
                                                    data-attachments="{{ $note->attachments->map(fn($a) => ['id' => $a->id, 'url' => $a->secure_url, 'thumbnail_url' => $a->thumbnailUrl(400)])->toJson() }}"
                                                    onclick="openEditNoteModal(this)">
                                                    <span class="material-symbols-outlined fn-icon-sm">edit</span>
                                                    Chỉnh sửa
                                                </a>
                                            </li>
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
                                                        Khoá bằng mật khẩu
                                                    </a>
                                                </li>
                                            @endif
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
                                    <p class="fn-note-excerpt">{{ Str::limit(strip_tags($note->content), 120) }}</p>

                                    {{-- Meta --}}
                                    <div class="fn-note-meta">
                                        <span class="fn-note-date">{{ $note->updated_at->diffForHumans() }}</span>
                                        <div class="d-flex align-items-center gap-1">
                                            @if($note->is_pinned)
                                                <span class="material-symbols-outlined fn-pin-star"
                                                    style="font-variation-settings:'FILL' 1;">star</span>
                                            @endif
                                            @if($note->is_locked)
                                                <span class="material-symbols-outlined fn-lock-badge"
                                                    title="Ghi chú đã khoá">lock</span>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5 text-muted fn-empty-state">
                        @if(isset($searchQuery) && $searchQuery)
                            <span class="material-symbols-outlined d-block mb-3">search_off</span>
                            <p class="small opacity-75">Không tìm thấy kết quả cho "{{ $searchQuery }}".</p>
                        @else
                            <span class="material-symbols-outlined d-block mb-3">note_add</span>
                            <p class="small opacity-75">Bạn chưa tạo ghi chú nào.<br>Hãy bắt đầu bằng cách tạo ghi chú đầu tiên!</p>
                        @endif
                    </div>
                @endif

            </div>
        </div>

    </div>

    @include('components::note-modal')
    @include('components::note-lock-modals')
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/note-lock.css') }}">
@endpush

@push('scripts')
    <script>
        window.FN_STORE_URL = '{{ route("notes.store") }}';
        window.FN_LABEL_STORE_URL = '{{ route("labels.store") }}';
    </script>
    <script src="{{ asset('assets/js/note-lock.js') }}"></script>
    <script src="{{ asset('assets/js/notes.js') }}"></script>
    <script src="{{ asset('assets/js/labels.js') }}"></script>
@endpush