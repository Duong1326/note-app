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
                <div class="fn-modal-field fn-modal-labels-container">
                    <label class="fn-modal-labels-title">Labels</label>
                    <div class="fn-modal-chips" id="modalLabelsChips">
                        @foreach($labels as $label)
                            <label class="fn-checkbox-label" for="modal_label_{{ $label->id }}">
                                <input type="checkbox" name="label_ids[]" id="modal_label_{{ $label->id }}"
                                    value="{{ $label->id }}" class="fn-checkbox-input" />
                                <span class="fn-checkbox-box">
                                    <span class="material-symbols-outlined check-icon">check</span>
                                </span>
                                <span class="fn-checkbox-text">{{ $label->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    {{-- Add Label Inline Button --}}
                    <div class="fn-modal-add-label-wrapper">
                        <button type="button" class="fn-modal-add-label-btn" id="modalAddLabelBtn" onclick="toggleModalAddLabelForm()">
                            <span class="material-symbols-outlined" style="font-size:16px;">add</span> Add Label
                        </button>
                        <input type="text" id="modalNewLabelInput" class="fn-modal-add-label-input d-none" 
                            placeholder="Type and enter..." 
                            onkeydown="if(event.key==='Enter'){ event.preventDefault(); createLabelFromModal(); } else if(event.key==='Escape') { event.preventDefault(); toggleModalAddLabelForm(); }"
                            onblur="setTimeout(() => { toggleModalAddLabelForm(true) }, 200)">
                    </div>
                </div>

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
                    <button type="button" class="fn-modal-btn-cancel" id="btnCancelNote">Cancel</button>
                    <button type="submit" class="fn-modal-btn-save">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
@endpush


