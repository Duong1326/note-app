/**
 * note-modal.js – Create / Edit note modal logic and form submission.
 * Depends on: app.js (apiFetch, showToast)
 *             note-cards.js (patchNoteCard, prependNoteCard, moveCardToTopOfUnpinned)
 *             note-attachments.js (_pendingFiles, _existingAttachments, renderExistingAttachments,
 *                                  uploadPendingFiles, showAttachmentSection)
 *             note-lock.js (getLockToken, clearLockToken)
 */

// ═══════════════════════════════════════════════════
// Modal Thumbnail Preview
// ═══════════════════════════════════════════════════

/**
 * Update the thumbnail preview banner at the top of the modal.
 * Priority: first existing attachment → first pending file → hide.
 */
function updateModalThumbnail() {
    const preview = document.getElementById('modalThumbPreview');
    const img = document.getElementById('modalThumbImage');
    if (!preview || !img) return;

    let src = null;

    // Check existing attachments first
    if (_existingAttachments.length > 0) {
        src = _existingAttachments[0].thumbnail_url || _existingAttachments[0].url;
    }
    // Fallback to first pending file
    else if (_pendingFiles.length > 0) {
        src = URL.createObjectURL(_pendingFiles[0]);
    }

    if (src) {
        img.src = src;
        preview.classList.remove('d-none');
    } else {
        img.src = '';
        preview.classList.add('d-none');
    }
}

/**
 * Remove the modal thumbnail. If the thumbnail came from an existing
 * attachment, delete it from the server. If it came from a pending file,
 * remove it from the queue.
 */
async function removeModalThumbnail() {
    if (_existingAttachments.length > 0) {
        const att = _existingAttachments[0];
        const noteId = att.note_id ?? _editingNoteId;

        // Server-side deletion for saved attachments
        if (att.id && noteId) {
            try {
                const res = await apiFetch(`/notes/${noteId}/attachments/${att.id}`, 'DELETE');
                if (!res.ok) throw new Error('Xóa ảnh thất bại');
                _existingAttachments = _existingAttachments.filter(a => a.id !== att.id);

                // Also remove from the attachment grid if present
                const gridThumb = document.querySelector(`.fn-attachment-thumb[data-attachment-id="${att.id}"]`);
                if (gridThumb) gridThumb.remove();

                // Update the note card on the dashboard
                const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
                if (col) {
                    updateCardThumbnail(col, _existingAttachments);
                    const editBtn = col.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
                    if (editBtn) editBtn.dataset.attachments = JSON.stringify(_existingAttachments);
                }
            } catch (err) {
                showToast(err.message || 'Không thể xóa ảnh', 'error');
                return;
            }
        } else {
            _existingAttachments.shift();
        }
    } else if (_pendingFiles.length > 0) {
        _pendingFiles.shift();
        renderPendingPreviews();
    }
    updateModalThumbnail();
}

// ═══════════════════════════════════════════════════
// State
// ═══════════════════════════════════════════════════

let _editingNoteId = null;

/**
 * One-time unlock token for editing a locked note.
 * Set when the user successfully unlocks a note to open the edit modal.
 * Sent as X-Note-Token on form submit, then cleared when the modal closes.
 */
let _editLockToken = null;

/**
 * Snapshot of the note state when the edit modal was opened.
 * Used to detect if the user actually made any changes before saving.
 */
let _originalSnapshot = null;

// ═══════════════════════════════════════════════════
// Open / Close Modal
// ═══════════════════════════════════════════════════

function openNewNoteModal() {
    _editingNoteId = null;
    _pendingFiles = [];
    _existingAttachments = [];

    document.querySelector('.fn-modal-title').innerText = 'Ghi chú mới';
    document.getElementById('createNoteForm')?.reset();
    clearEditorContent();
    const ea = document.getElementById('existingAttachments');
    if (ea) ea.innerHTML = '';
    const pp = document.getElementById('pendingPreviews');
    if (pp) pp.innerHTML = '';

    // Hide attachment section
    const section = document.getElementById('attachmentSection');
    section?.classList.add('d-none');
    section?.classList.remove('d-flex');
    document.getElementById('btnToggleAttachment')?.classList.remove('active');

    // Hide modal thumbnail
    updateModalThumbnail();

    _showModal();
    setTimeout(() => document.getElementById('modalNoteTitle')?.focus(), 350);
}

/**
 * Open the edit modal. `token` is passed from requireUnlock callback
 * when the note is locked — it authorizes the PUT /notes/{id} request.
 */
function openEditNoteModal(btn, token = null) {
    _editingNoteId = btn.dataset.id;
    _editLockToken = token;  // may be null for unlocked notes
    _pendingFiles = [];
    _existingAttachments = JSON.parse(btn.dataset.attachments || '[]');

    const labels = JSON.parse(btn.dataset.labels || '[]');
    document.getElementById('modalNoteTitle').value = btn.dataset.title;
    setEditorContent(btn.dataset.content ?? '');

    document.querySelectorAll('input[name="label_ids[]"]').forEach(cb => {
        cb.checked = labels.includes(parseInt(cb.value));
    });

    // Snapshot để phát hiện thay đổi khi submit
    _originalSnapshot = {
        title: btn.dataset.title,
        content: btn.dataset.content ?? '',
        labelIds: labels.map(String).sort().join(','),
    };

    renderExistingAttachments();
    const pp = document.getElementById('pendingPreviews');
    if (pp) pp.innerHTML = '';

    // Always hide attachment section when opening edit modal
    // (thumbnail is shown above title; user can re-open via toolbar button)
    const section = document.getElementById('attachmentSection');
    section?.classList.add('d-none');
    section?.classList.remove('d-flex');
    document.getElementById('btnToggleAttachment')?.classList.remove('active');

    // Show modal thumbnail preview
    updateModalThumbnail();

    document.querySelector('.fn-modal-title').innerText = 'Chỉnh sửa ghi chú';
    _showModal();

    // Start auto-save watch with the current baseline values
    if (typeof autoSaveReset === 'function') {
        autoSaveReset(
            btn.dataset.title,
            btn.dataset.content ?? '',
            JSON.parse(btn.dataset.labels || '[]')
        );
    }
}

function closeNewNoteModal() {
    document.getElementById('newNoteModal')?.classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('createNoteForm')?.reset();

    _editingNoteId = null;
    _editLockToken = null;  // discard any unused token
    _pendingFiles = [];
    _existingAttachments = [];
    _originalSnapshot = null;

    // Reset modal thumbnail
    const preview = document.getElementById('modalThumbPreview');
    const img = document.getElementById('modalThumbImage');
    if (preview) preview.classList.add('d-none');
    if (img) img.src = '';

    // Reset editor content
    clearEditorContent();
    hideSlashMenu();

    // Reset save button state
    const saveBtn = document.querySelector('#createNoteForm .fn-modal-btn-save');
    if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Lưu thay đổi';
    }

    // Cancel any pending auto-save
    if (typeof autoSaveCancel === 'function') autoSaveCancel();
}

function _showModal() {
    document.getElementById('newNoteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// ═══════════════════════════════════════════════════
// Form Submission
// ═══════════════════════════════════════════════════

async function submitNoteForm() {
    const title = document.getElementById('modalNoteTitle').value.trim();

    if (!title) {
        showToast('Vui lòng nhập tiêu đề ghi chú', 'error');
        document.getElementById('modalNoteTitle').focus();
        return;
    }

    const labelIds  = [...document.querySelectorAll('input[name="label_ids[]"]:checked')].map(cb => cb.value);
    const isEditing = _editingNoteId !== null;

    // Read content BEFORE upload for change detection only
    const contentBeforeUpload = getEditorContent();

    // ── Kiểm tra thay đổi (chỉ áp dụng khi đang edit) ──
    if (isEditing && _originalSnapshot) {
        const currentLabelIds = labelIds.map(String).sort().join(',');
        const hasChanges =
            title !== _originalSnapshot.title ||
            contentBeforeUpload !== _originalSnapshot.content ||
            currentLabelIds !== _originalSnapshot.labelIds ||
            _pendingFiles.length > 0;

        if (!hasChanges) {
            closeNewNoteModal();
            return;
        }
    }

    // ── Loading state on save button ──
    const saveBtn = document.querySelector('#createNoteForm .fn-modal-btn-save');
    const saveBtnOriginalHtml = saveBtn?.innerHTML;
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="fn-btn-spinner"></span> Đang lưu…';
    }

    try {
        let noteId;
        const lockToken = _editLockToken;

        if (isEditing) {
            // ── EDIT: upload inline images first (noteId is known) ──
            await uploadInlineContentImages(_editingNoteId, lockToken);

            const content = getEditorContent();  // now has real Cloudinary URLs
            const body = new URLSearchParams({ title, content });
            labelIds.forEach(id => body.append('label_ids[]', id));

            const headers = lockToken ? { 'X-Note-Token': lockToken } : {};
            const res = await apiFetch(`/notes/${_editingNoteId}`, 'PUT', body, headers);
            const data = await res.json();

            if (!res.ok) {
                const firstError = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : (data.message || 'Có lỗi xảy ra');
                showToast(firstError, 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = saveBtnOriginalHtml; }
                return;
            }

            noteId = data.note.id;

            // Optimistic card update
            const filesToUpload = [..._pendingFiles];
            const existingAtts  = [..._existingAttachments];
            const uploadToken   = lockToken;

            const tempThumbs = filesToUpload.map(f => ({
                id: null, url: URL.createObjectURL(f), thumbnail_url: URL.createObjectURL(f),
            }));
            data.note.attachments = [...existingAtts, ...tempThumbs];
            patchNoteCard(_editingNoteId, data.note);
            const col = document.querySelector(`.note-col[data-note-id="${_editingNoteId}"]`);
            moveCardToTopOfUnpinned(col);

            closeNewNoteModal();

            // Upload thumbnail attachments in background
            if (filesToUpload.length > 0) {
                const uploadResults = await uploadPendingFilesParallel(noteId, filesToUpload, uploadToken);
                const allAttachments = [...existingAtts, ...uploadResults];
                const cardCol = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
                if (cardCol) {
                    updateCardThumbnail(cardCol, allAttachments);
                    const editBtn = cardCol.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
                    if (editBtn) editBtn.dataset.attachments = JSON.stringify(allAttachments);
                }
            }

        } else {
            // ── NEW NOTE: POST first (inline images need noteId) ──
            const bodyFirst = new URLSearchParams({ title, content: contentBeforeUpload });
            labelIds.forEach(id => bodyFirst.append('label_ids[]', id));

            const resFirst = await apiFetch(window.FN_STORE_URL, 'POST', bodyFirst);
            const dataFirst = await resFirst.json();

            if (!resFirst.ok) {
                const firstError = dataFirst.errors
                    ? Object.values(dataFirst.errors).flat()[0]
                    : (dataFirst.message || 'Có lỗi xảy ra');
                showToast(firstError, 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = saveBtnOriginalHtml; }
                return;
            }

            noteId = dataFirst.note.id;

            // Upload inline images now that we have noteId
            await uploadInlineContentImages(noteId, null);
            const finalContent = getEditorContent();  // real URLs now

            // Update content with real URLs if any inline images were present
            const hasInlineImages = finalContent !== contentBeforeUpload;
            if (hasInlineImages) {
                const bodyUpdate = new URLSearchParams({ title, content: finalContent });
                labelIds.forEach(id => bodyUpdate.append('label_ids[]', id));
                await apiFetch(`/notes/${noteId}`, 'PUT', bodyUpdate).catch(() => {});
            }

            // Optimistic card
            const filesToUpload = [..._pendingFiles];
            const tempThumbs = filesToUpload.map(f => ({
                id: null, url: URL.createObjectURL(f), thumbnail_url: URL.createObjectURL(f),
            }));
            dataFirst.note.content    = finalContent;
            dataFirst.note.attachments = [...(dataFirst.note.attachments ?? []), ...tempThumbs];
            prependNoteCard(dataFirst.note);

            closeNewNoteModal();

            // Upload thumbnail attachments in background
            if (filesToUpload.length > 0) {
                const uploadResults = await uploadPendingFilesParallel(noteId, filesToUpload, null);
                const allAttachments = [...uploadResults];
                const cardCol = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
                if (cardCol) {
                    updateCardThumbnail(cardCol, allAttachments);
                    const editBtn = cardCol.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
                    if (editBtn) editBtn.dataset.attachments = JSON.stringify(allAttachments);
                }
            }
        }

    } catch {
        showToast('Lỗi kết nối, vui lòng thử lại', 'error');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = saveBtnOriginalHtml; }
    }
}

