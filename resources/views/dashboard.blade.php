@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="fn-dashboard">

    {{-- ═══ Welcome Section ═══ --}}
    <section class="fn-welcome fn-animate-in">
        <div class="row align-items-end justify-content-between g-4">
            <div class="col-12 col-md-8">
                <h2 class="fn-welcome-title">Welcome back, {{ Auth::user()->name }}</h2>
                <p class="fn-welcome-sub">Ready to capture your thoughts?</p>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <button type="button" class="fn-btn-new-note" onclick="openNewNoteModal()">
                    <span class="material-symbols-outlined">add</span>
                    New Note
                </button>
            </div>
        </div>
    </section>

    {{-- ═══ Quick Actions ═══ --}}
    @include('components::quick-actions')

    {{-- ═══ Recent Notes ═══ --}}
    <div class="row g-4">
        <div class="col-12">

            {{-- Section Header --}}
            <div class="fn-section-header">
                <div class="d-flex align-items-center gap-3">
                    <h3 class="fn-section-title">
                        @if(isset($searchQuery) && $searchQuery)
                            Search Results for "{{ $searchQuery }}"
                        @else
                            Recent Notes
                        @endif
                    </h3>
                    <div class="fn-view-toggle">
                        <button type="button" class="fn-toggle-btn active" id="btnGridView"
                            onclick="setNotesView('grid')" title="Dạng lưới">
                            <span class="material-symbols-outlined" style="font-size:18px;">grid_view</span>
                        </button>
                        <button type="button" class="fn-toggle-btn" id="btnListView"
                            onclick="setNotesView('list')" title="Dạng danh sách">
                            <span class="material-symbols-outlined" style="font-size:18px;">view_list</span>
                        </button>
                    </div>
                </div>
                <a href="{{ route('notes.index') }}" class="fn-section-link">View archive</a>
            </div>

            {{-- Notes Grid/List --}}
            @if($recentNotes->count() > 0)
                <div class="row g-3" id="notesContainer">
                    @foreach($recentNotes as $note)
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-animate-in note-col" data-note-id="{{ $note->id }}">
                            <div class="fn-note-card">

                                {{-- Dropdown Menu --}}
                                <div class="dropdown fn-note-dropdown">
                                    <span class="material-symbols-outlined fn-note-more"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        more_vert
                                    </span>
                                    <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu">
                                        {{-- Edit --}}
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                href="javascript:void(0)"
                                                data-id="{{ $note->id }}"
                                                data-title="{{ $note->title }}"
                                                data-content="{{ $note->content }}"
                                                data-labels="{{ $note->labels->pluck('id')->toJson() }}"
                                                onclick="openEditNoteModal(this)">
                                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                                                Sửa
                                            </a>
                                        </li>
                                        {{-- Pin / Unpin (AJAX) --}}
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                                                href="javascript:void(0)"
                                                onclick="togglePinAjax({{ $note->id }}, {{ $note->is_pinned ? 'true' : 'false' }})">
                                                <span class="material-symbols-outlined"
                                                    style="font-size:18px;{{ $note->is_pinned ? "font-variation-settings:'FILL' 1;" : '' }}">
                                                    push_pin
                                                </span>
                                                {{ $note->is_pinned ? 'Bỏ ghim' : 'Ghim' }}
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- Delete (AJAX) --}}
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger"
                                                href="javascript:void(0)"
                                                onclick="deleteNoteAjax({{ $note->id }})">
                                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
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
                                            <span class="fn-label-badge">{{ $label->name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Excerpt --}}
                                <p class="fn-note-excerpt">{{ Str::limit(strip_tags($note->content), 120) }}</p>

                                {{-- Meta --}}
                                <div class="fn-note-meta">
                                    <span class="fn-note-date">
                                        {{ $note->updated_at->diffForHumans() }}
                                    </span>
                                    @if($note->is_pinned)
                                        <span class="material-symbols-outlined fn-pin-star"
                                            style="font-variation-settings:'FILL' 1;">star</span>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="fn-empty-state">
                    @if(isset($searchQuery) && $searchQuery)
                        <span class="material-symbols-outlined">search_off</span>
                        <p>No results found for "{{ $searchQuery }}".</p>
                    @else
                        <span class="material-symbols-outlined">note_add</span>
                        <p>You haven't created any notes yet.<br>Start by creating your first note!</p>
                    @endif
                </div>
            @endif

        </div>
    </div>

</div>

@include('components::note-modal')
@endsection

@push('scripts')
{{-- Pass server-side config to JS --}}
<script>
    window.FN_STORE_URL = '{{ route("notes.store") }}';
</script>
<script src="{{ asset('assets/js/notes.js') }}"></script>
@endpush


