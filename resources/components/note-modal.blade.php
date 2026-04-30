{{-- ═══ Note Modal Overlay (Create / Edit) ═══ --}}
<div class="fn-modal-overlay" id="newNoteModal">
    <div class="fn-modal-card">

        {{-- Header --}}
        <div class="fn-modal-header">
            <div class="d-flex align-items-center gap-2">
                <div class="fn-modal-icon">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <h2 class="fn-modal-title">Ghi chú mới</h2>
            </div>

            {{-- Header right: auto-save status + close --}}
            <div class="d-flex align-items-center gap-2">
                {{-- Auto-save status indicator (injected by auto-save.js) --}}
                <span class="fn-autosave-status" id="modalAutoSaveStatus" aria-live="polite"></span>

                <button type="button" class="fn-modal-close" onclick="closeNewNoteModal()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>

        {{-- Form (no footer — auto-save handles saving) --}}
        <form action="{{ route('notes.store') }}" method="POST" id="createNoteForm">
            @csrf
            <div class="fn-modal-body">

                {{-- Thumbnail Preview (shown above title when image exists) --}}
                <div class="fn-modal-thumb-preview d-none" id="modalThumbPreview">
                    <img src="" alt="Note thumbnail" id="modalThumbImage" onclick="openLightbox(this.src)">
                    <button type="button" class="fn-modal-thumb-remove" id="modalThumbRemoveBtn" title="Xóa ảnh bìa"
                        onclick="removeModalThumbnail()">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                {{-- Inline image picker (shown above title when attach btn clicked) --}}
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

                {{-- Title + hover attach button --}}
                <div class="fn-modal-field fn-title-row">
                    <input type="text" name="title" id="modalNoteTitle" class="fn-modal-title-input"
                        placeholder="Tiêu đề ghi chú" required />
                    <button type="button" class="fn-title-attach-btn fn-modal-tool-btn fn-attach-toggle-btn"
                        id="btnToggleAttachment" title="Đính kèm ảnh"
                        onclick="toggleAttachmentSection()">
                        <span class="material-symbols-outlined">image</span>
                    </button>
                </div>

                {{-- Labels --}}
                <div class="fn-modal-field fn-modal-labels-container">
                    <label class="fn-modal-labels-title">Nhãn</label>
                    <div class="fn-modal-chips" id="modalLabelsChips">
                        @foreach($labels as $label)
                            <label class="fn-checkbox-label" for="modal_label_{{ $label->id }}">
                                <input type="checkbox" name="label_ids[]" id="modal_label_{{ $label->id }}"
                                    value="{{ $label->id }}" class="fn-checkbox-input" />
                                <span class="fn-checkbox-text">{{ $label->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    {{-- Inline label creation --}}
                    <div class="fn-modal-add-label-wrapper">
                        <button type="button" class="fn-modal-add-label-btn" id="modalAddLabelBtn"
                            onclick="toggleModalAddLabelForm()">
                            <span class="material-symbols-outlined fn-icon-sm">add</span>
                            Thêm nhãn
                        </button>
                        <input type="text" id="modalNewLabelInput" class="fn-modal-add-label-input d-none"
                            placeholder="Nhập và nhấn Enter..."
                            onkeydown="if(event.key==='Enter'){ event.preventDefault(); createLabelFromModal(); } else if(event.key==='Escape') { event.preventDefault(); toggleModalAddLabelForm(true); }"
                            onblur="setTimeout(() => onModalLabelBlur(), 150)">
                    </div>
                </div>

                {{-- Content (contenteditable for inline images) --}}
                <div class="fn-modal-field fn-editor-wrapper">
                    <div id="modalNoteContent" class="fn-modal-content-input" contenteditable="true"
                        data-placeholder="Nhấn &lsquo;/&rsquo; để chèn khối • Bắt đầu viết ý tưởng..."></div>
                </div>

                {{-- Image Attachments (grids only – picker handled by openImgPicker) --}}
                <div class="fn-modal-field fn-attachment-section d-none" id="attachmentSection">
                    <label class="fn-modal-labels-title d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fn-icon-sm">image</span>
                        Hình ảnh
                    </label>

                    {{-- Existing attachments (edit mode) --}}
                    <div class="fn-attachment-grid" id="existingAttachments"></div>

                    {{-- Pending previews (queued for upload) --}}
                    <div class="fn-attachment-grid" id="pendingPreviews"></div>
                </div>

            </div>
        </form>

    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
@endpush