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
                <a href="{{ route('notes.index') }}" class="fn-btn-new-note">
                    <span class="material-symbols-outlined">add</span>
                    New Note
                </a>
            </div>
        </div>
    </section>

    {{-- ═══ Quick Actions Grid ═══ --}}
    <section class="fn-quick-actions fn-animate-in">
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg-3">
                <a href="{{ route('notes.index') }}" class="fn-action-card">
                    <div class="fn-action-icon icon-new">
                        <span class="material-symbols-outlined">edit_note</span>
                    </div>
                    <div>
                        <div class="fn-action-title">New Note</div>
                        <p class="fn-action-desc">Create something new</p>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <a href="{{ route('notes.index') }}" class="fn-action-card">
                    <div class="fn-action-icon icon-all">
                        <span class="material-symbols-outlined">grid_view</span>
                    </div>
                    <div>
                        <div class="fn-action-title">View All Notes</div>
                        <p class="fn-action-desc">Access your library</p>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="fn-action-card">
                    <div class="fn-action-icon icon-fav">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
                    </div>
                    <div>
                        <div class="fn-action-title">Favorites</div>
                        <p class="fn-action-desc">{{ $pinnedNotes->count() }} important items</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="fn-action-card">
                    <div class="fn-action-icon icon-recent">
                        <span class="material-symbols-outlined">history</span>
                    </div>
                    <div>
                        <div class="fn-action-title">Recently Edited</div>
                        <p class="fn-action-desc">Resume working</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ Main Workspace Layout ═══ --}}
    <div class="row g-4">

        {{-- ── Recent Notes (Left Column) ── --}}
        <div class="col-12 col-lg-8">
            <div class="fn-section-header">
                <h3 class="fn-section-title">Recent Notes</h3>
                <a href="{{ route('notes.index') }}" class="fn-section-link">View archive</a>
            </div>

            @if($recentNotes->count() > 0)
                <div class="row g-3">
                    @foreach($recentNotes as $note)
                        <div class="col-12 col-md-6 fn-animate-in">
                            <div class="fn-note-card">
                                <div class="fn-note-card-header">
                                    <h4 class="fn-note-title">{{ $note->title }}</h4>
                                    <span class="material-symbols-outlined fn-note-more">more_vert</span>
                                </div>

                                @if($note->labels->count() > 0)
                                    <div class="fn-note-labels">
                                        @foreach($note->labels->take(3) as $label)
                                            <span class="fn-label-badge">{{ $label->name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <p class="fn-note-excerpt">{{ Str::limit(strip_tags($note->content), 120) }}</p>

                                <div class="fn-note-meta">
                                    <span class="fn-note-date">
                                        Last updated {{ $note->updated_at->diffForHumans() }}
                                    </span>
                                    @if($note->is_pinned)
                                        <span class="material-symbols-outlined fn-pin-star" style="font-variation-settings: 'FILL' 1;">star</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="fn-empty-state">
                    <span class="material-symbols-outlined">note_add</span>
                    <p>You haven't created any notes yet.<br>Start by creating your first note!</p>
                </div>
            @endif
        </div>

        {{-- ── Right Column: Activity & Pinned ── --}}
        <div class="col-12 col-lg-4">

            {{-- Activity Widget --}}
            <div class="mb-4 fn-animate-in">
                <h3 class="fn-section-label mb-3 px-1">Activity</h3>
                <div class="fn-activity-card">
                    <div class="fn-activity-content">
                        <div class="fn-activity-icon">
                            <span class="material-symbols-outlined">analytics</span>
                        </div>
                        <div class="fn-activity-count">{{ $weeklyNotes }}</div>
                        <div class="fn-activity-label">notes created this week</div>
                        <div class="fn-progress-track">
                            @php
                                $progress = $totalNotes > 0 ? min(100, round(($weeklyNotes / $totalNotes) * 100)) : 0;
                            @endphp
                            <div class="fn-progress-fill" style="width: {{ $progress }}%"></div>
                        </div>
                        <div class="fn-progress-label">{{ $progress }}% of total {{ $totalNotes }} notes</div>
                    </div>
                </div>
            </div>

            {{-- Stats Summary --}}
            <div class="mb-4 fn-animate-in">
                <h3 class="fn-section-label mb-3 px-1">Overview</h3>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="fn-note-card text-center py-4">
                            <div class="fn-activity-count" style="font-size: 1.5rem;">{{ $totalNotes }}</div>
                            <div class="fn-note-date">Total Notes</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="fn-note-card text-center py-4">
                            <div class="fn-activity-count" style="font-size: 1.5rem;">{{ $labels->count() }}</div>
                            <div class="fn-note-date">Labels</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pinned / Favorites --}}
            <div class="fn-animate-in">
                <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                    <h3 class="fn-section-label mb-0">Pinned</h3>
                    <span class="material-symbols-outlined" style="font-size: 18px; color: var(--fn-on-surface-variant);">push_pin</span>
                </div>

                @if($pinnedNotes->count() > 0)
                    @foreach($pinnedNotes as $pinned)
                        <div class="fn-pinned-item">
                            <div class="fn-pinned-icon">
                                <span class="material-symbols-outlined" style="font-size: 18px; font-variation-settings: 'FILL' 1;">star</span>
                            </div>
                            <div class="fn-pinned-info">
                                <p class="fn-pinned-title">{{ $pinned->title }}</p>
                                <p class="fn-pinned-date">Pinned {{ $pinned->pinned_at ? $pinned->pinned_at->diffForHumans() : $pinned->updated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="fn-empty-state" style="padding: 1.5rem;">
                        <span class="material-symbols-outlined" style="font-size: 32px;">push_pin</span>
                        <p class="mb-0">No pinned notes yet</p>
                    </div>
                @endif
            </div>

            {{-- Labels --}}
            @if($labels->count() > 0)
                <div class="mt-4 fn-animate-in">
                    <h3 class="fn-section-label mb-3 px-1">Your Labels</h3>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($labels as $label)
                            <span class="fn-label-badge" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">{{ $label->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
