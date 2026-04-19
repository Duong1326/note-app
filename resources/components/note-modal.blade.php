{{-- ═══ Modal Overlay: Create New Note ═══ --}}
<div class="fn-modal-overlay" id="newNoteModal">
    <div class="fn-modal-card">
        {{-- Modal Header --}}
        <div class="fn-modal-header">
            <div class="fn-modal-header-left">
                <div class="fn-modal-icon">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <h2 class="fn-modal-title">New Note</h2>
            </div>
            <button type="button" class="fn-modal-close" onclick="closeNewNoteModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Modal Form --}}
        <form action="{{ route('notes.store') }}" method="POST" id="createNoteForm">
            @csrf
            <div class="fn-modal-body">
                {{-- Title --}}
                <div class="fn-modal-field">
                    <input type="text" name="title" id="modalNoteTitle" class="fn-modal-title-input"
                        placeholder="Note Title" required />
                </div>

                {{-- Labels --}}
                @if($labels->count() > 0)
                    <div class="fn-modal-field fn-modal-labels">
                        <label class="fn-modal-field-label">
                            <span class="material-symbols-outlined">label</span>
                            Labels
                        </label>
                        <div class="fn-modal-chips">
                            @foreach($labels as $label)
                                <label class="fn-chip" for="modal_label_{{ $label->id }}">
                                    <input type="checkbox" name="label_ids[]" id="modal_label_{{ $label->id }}"
                                        value="{{ $label->id }}" />
                                    <span class="fn-chip-text">{{ $label->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Content --}}
                <div class="fn-modal-field">
                    <textarea name="content" id="modalNoteContent" class="fn-modal-content-input"
                        placeholder="Start typing your ideas here..." rows="8"></textarea>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="fn-modal-footer">
                <div class="fn-modal-toolbar">
                    <button type="button" class="fn-modal-tool-btn" title="Attach image">
                        <span class="material-symbols-outlined">image</span>
                    </button>
                    <button type="button" class="fn-modal-tool-btn" title="Add attachment">
                        <span class="material-symbols-outlined">attachment</span>
                    </button>
                    <button type="button" class="fn-modal-tool-btn" title="Add list">
                        <span class="material-symbols-outlined">list</span>
                    </button>
                </div>
                <div class="fn-modal-actions">
                    <button type="submit" class="fn-modal-btn-save">
                        <span class="material-symbols-outlined">save</span>
                        Save Note
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
@endpush


