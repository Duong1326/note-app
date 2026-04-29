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
            <button type="button" class="fn-modal-close" onclick="closeNewNoteModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Form --}}
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

                {{-- Title --}}
                <div class="fn-modal-field">
                    <input type="text" name="title" id="modalNoteTitle" class="fn-modal-title-input"
                        placeholder="Tiêu đề ghi chú" required />
                </div>

                {{-- Labels --}}
                <div class="fn-modal-field fn-modal-labels-container">
                    <label class="fn-modal-labels-title">Nhãn</label>
                    <div class="fn-modal-chips" id="modalLabelsChips">
                        @foreach($labels as $label)
                            <label class="fn-checkbox-label" for="modal_label_{{ $label->id }}">
                                <input type="checkbox" name="label_ids[]" id="modal_label_{{ $label->id }}"
                                    value="{{ $label->id }}" class="fn-checkbox-input" />
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

                    {{-- Slash Command Menu --}}
                    <div class="fn-slash-menu d-none" id="slashCommandMenu">
                        <div class="fn-slash-header">Khối cơ bản</div>
                        <div class="fn-slash-list"></div>
                        <div class="fn-slash-footer">
                            <span class="fn-slash-hint"><kbd>↑↓</kbd> chọn</span>
                            <span class="fn-slash-hint"><kbd>↵</kbd> xác nhận</span>
                            <span class="fn-slash-hint"><kbd>esc</kbd> đóng</span>
                        </div>
                    </div>
                </div>

                {{-- Image Attachments --}}
                <div class="fn-modal-field fn-attachment-section d-none" id="attachmentSection">
                    <label class="fn-modal-labels-title d-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fn-icon-sm">image</span>
                        Hình ảnh
                    </label>

                    {{-- Existing attachments (edit mode) --}}
                    <div class="fn-attachment-grid" id="existingAttachments"></div>

                    {{-- Pending previews (queued for upload) --}}
                    <div class="fn-attachment-grid" id="pendingPreviews"></div>

                    {{-- Drop zone --}}
                    <label class="fn-attachment-dropzone" id="attachmentDropzone" for="attachmentFileInput">
                        <span class="material-symbols-outlined">add_photo_alternate</span>
                        <span>Nhấn hoặc kéo thả ảnh vào đây</span>
                        <span class="fn-attachment-hint">JPEG, PNG, GIF, WebP &bull; tối đa 10 MB mỗi ảnh</span>
                    </label>
                    <input type="file" id="attachmentFileInput"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple class="d-none">
                </div>

            </div>

            {{-- Footer --}}
            <div class="fn-modal-footer">
                <div class="fn-modal-toolbar">
                    <button type="button" class="fn-modal-tool-btn fn-attach-toggle-btn" id="btnToggleAttachment"
                        title="Đính kèm ảnh" onclick="toggleAttachmentSection()">
                        <span class="material-symbols-outlined">image</span>
                    </button>
                    <button type="button" class="fn-modal-tool-btn" title="Thêm danh sách">
                        <span class="material-symbols-outlined">list</span>
                    </button>
                </div>
                <div class="fn-modal-actions">
                    <button type="button" class="fn-modal-btn-cancel" id="btnCancelNote"
                        onclick="closeNewNoteModal()">Hủy</button>
                    <button type="submit" class="fn-modal-btn-save">Lưu thay đổi</button>
                </div>
            </div>
        </form>

    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/note-create.css') }}">
@endpush