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

@push('scripts')
    <script>
        function openNewNoteModal() {
            const modal = document.getElementById('newNoteModal');
            
            // Set mode to Create
            document.querySelector('.fn-modal-title').innerText = 'New Note';
            const form = document.getElementById('createNoteForm');
            form.action = "{{ route('notes.store') }}";
            const putMethod = document.getElementById('methodPut');
            if (putMethod) putMethod.remove();
            
            form.reset();

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Focus title input after animation
            setTimeout(() => {
                document.getElementById('modalNoteTitle').focus();
            }, 350);
        }

        function openEditNoteModal(btn) {
            const id = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            const content = btn.getAttribute('data-content');
            const labels = JSON.parse(btn.getAttribute('data-labels') || '[]');

            document.getElementById('modalNoteTitle').value = title;
            document.getElementById('modalNoteContent').value = content || '';

            // Check labels
            const checkboxes = document.querySelectorAll('input[name="label_ids[]"]');
            checkboxes.forEach(cb => {
                cb.checked = labels.includes(parseInt(cb.value));
            });

            // Set mode to Edit
            document.querySelector('.fn-modal-title').innerText = 'Edit Note';
            const form = document.getElementById('createNoteForm');
            form.action = `/notes/${id}`;
            
            // Add method spoofing for PUT
            if (!document.getElementById('methodPut')) {
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'PUT';
                methodInput.id = 'methodPut';
                form.appendChild(methodInput);
            }

            const modal = document.getElementById('newNoteModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeNewNoteModal() {
            const modal = document.getElementById('newNoteModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('createNoteForm').reset();
            const putMethod = document.getElementById('methodPut');
            if (putMethod) putMethod.remove();
        }

        // Close on overlay click (not card)
        document.getElementById('newNoteModal').addEventListener('click', function (e) {
            if (e.target === this) closeNewNoteModal();
        });

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeNewNoteModal();
        });

        // Ctrl/Cmd + S to save
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const modal = document.getElementById('newNoteModal');
                if (modal.classList.contains('show')) {
                    e.preventDefault();
                    document.getElementById('createNoteForm').submit();
                }
            }
        });
    </script>
@endpush
