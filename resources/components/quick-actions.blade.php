{{-- ═══ Quick Actions Grid ═══ --}}
<section class="fn-quick-actions fn-animate-in">
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="fn-action-card" onclick="openNewNoteModal()" style="cursor:pointer;">
                <div class="fn-action-icon icon-new">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <div>
                    <div class="fn-action-title">New Note</div>
                    <p class="fn-action-desc">Create something new</p>
                </div>
            </div>
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
