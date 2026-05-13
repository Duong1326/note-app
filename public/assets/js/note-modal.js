/**
 * note-modal.js – Note form submission and thumbnail helpers.
 *
 * This file has been refactored from the old overlay-modal system to work
 * with the full-page editor (notes/edit.blade.php + note-page.js).
 *
 * Depends on: app.js             (apiFetch, showToast)
 *             note-cards.js      (patchNoteCard, prependNoteCard, moveCardToTopOfUnpinned,
 *                                 updateCardThumbnail)
 *             note-attachments.js(_pendingFiles, _existingAttachments, renderExistingAttachments,
 *                                  uploadPendingFilesParallel, renderPendingPreviews, updateModalThumbnail)
 *             note-lock.js       (getLockToken, clearLockToken)
 */

// ═══════════════════════════════════════════════════
// Thumbnail Preview (top of editor)
// ═══════════════════════════════════════════════════

/**
 * Update the thumbnail preview banner at the top of the editor.
 * Priority: first existing attachment → first pending file → hide.
 */
function updateModalThumbnail() {
    const preview = document.getElementById('modalThumbPreview');
    const img     = document.getElementById('modalThumbImage');
    const btnAddCover = document.getElementById('btnToggleAttachment');
    if (!preview || !img) return;

    let src = null;

    if (_existingAttachments.length > 0) {
        src = _existingAttachments[0].thumbnail_url || _existingAttachments[0].url;
    } else if (_pendingFiles.length > 0) {
        src = URL.createObjectURL(_pendingFiles[0]);
    }

    if (src) {
        img.src = src;
        preview.classList.remove('d-none');
        if (btnAddCover) btnAddCover.classList.add('d-none');
    } else {
        img.src = '';
        preview.classList.add('d-none');
        if (btnAddCover) btnAddCover.classList.remove('d-none');
    }
}

/**
 * Remove the thumbnail. Deletes from server if it was a saved attachment,
 * or drops it from the pending queue if it was a local file.
 */
async function removeModalThumbnail() {
    if (_existingAttachments.length > 0) {
        const att    = _existingAttachments[0];
        const noteId = att.note_id ?? _editingNoteId;

        if (att.id && noteId) {
            try {
                const res = await apiFetch(`/notes/${noteId}/attachments/${att.id}`, 'DELETE');
                if (!res.ok) throw new Error('Xóa ảnh thất bại');
                _existingAttachments = _existingAttachments.filter(a => a.id !== att.id);

                const gridThumb = document.querySelector(`.fn-attachment-thumb[data-attachment-id="${att.id}"]`);
                if (gridThumb) gridThumb.remove();
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

/** ID of the note being edited. null = create mode.
 *  Declared as `var` so note-page.js can set it via window._editingNoteId. */
var _editingNoteId = null;

/** One-time unlock token for locked notes.
 *  Declared as `var` so note-page.js can set it via window._editLockToken. */
var _editLockToken = null;

// ═══════════════════════════════════════════════════
// Close / Reset
// ═══════════════════════════════════════════════════

/**
 * Reset editor state and fire cleanup callbacks.
 * On the full-page editor, note-page.js overrides this to also navigate away.
 */
function closeNewNoteModal() {
    _editingNoteId   = null;
    _editLockToken   = null;
    _pendingFiles    = [];
    _existingAttachments = [];

    // Reset thumbnail banner
    const preview = document.getElementById('modalThumbPreview');
    const img     = document.getElementById('modalThumbImage');
    if (preview) preview.classList.add('d-none');
    if (img)     img.src = '';

    // Reset editor content
    if (typeof clearEditorContent === 'function') clearEditorContent();
    if (typeof hideSlashMenu      === 'function') hideSlashMenu();

    // Cancel pending auto-save and close pickers
    if (typeof autoSaveCancel      === 'function') autoSaveCancel();
    if (typeof closeImgPicker      === 'function') closeImgPicker();
    if (typeof closeSlashImgPicker === 'function') closeSlashImgPicker();
}

// ═══════════════════════════════════════════════════
// Form Submission
// ═══════════════════════════════════════════════════

async function submitNoteForm() {
    const title = document.getElementById('modalNoteTitle')?.value.trim() ?? '';

    if (!title) {
        showToast('Vui lòng nhập tiêu đề ghi chú', 'error');
        document.getElementById('modalNoteTitle')?.focus();
        return;
    }

    // Cancel any pending auto-save timer (manual save takes priority)
    if (typeof autoSaveCancel === 'function') autoSaveCancel();

    const labelIds  = [...document.querySelectorAll('input[name="label_ids[]"]:checked')].map(cb => cb.value);
    const isEditing = _editingNoteId !== null;

    const contentBeforeUpload = typeof getEditorContent === 'function' ? getEditorContent() : '';

    try {
        let noteId;
        const lockToken = _editLockToken;

        if (isEditing) {
            // ── EDIT: PUT existing note ──────────────────────────────────────
            if (typeof uploadInlineContentImages === 'function') {
                await uploadInlineContentImages(_editingNoteId, lockToken);
            }

            const content = typeof getEditorContent === 'function' ? getEditorContent() : '';
            const body    = new URLSearchParams({ title, content });
            labelIds.forEach(id => body.append('label_ids[]', id));

            const headers = lockToken ? { 'X-Note-Token': lockToken } : {};
            const res     = await apiFetch(`/notes/${_editingNoteId}`, 'PUT', body, headers);
            const data    = await res.json();

            if (!res.ok) {
                const firstError = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : (data.message || 'Có lỗi xảy ra');
                showToast(firstError, 'error');
                return;
            }

            noteId = data.note.id;

            // Optimistic card update (no-ops on edit page — note-page.js overrides these)
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

            // Upload thumbnail attachments in background after navigation
            if (filesToUpload.length > 0) {
                const uploadResults  = await uploadPendingFilesParallel(noteId, filesToUpload, uploadToken);
                const allAttachments = [...existingAtts, ...uploadResults];
                const cardCol        = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
                if (cardCol && typeof updateCardThumbnail === 'function') {
                    updateCardThumbnail(cardCol, allAttachments);
                }
            }

        } else {
            // ── CREATE: POST new note ────────────────────────────────────────
            const bodyFirst = new URLSearchParams({ title, content: contentBeforeUpload });
            labelIds.forEach(id => bodyFirst.append('label_ids[]', id));

            const resFirst  = await apiFetch(window.FN_STORE_URL, 'POST', bodyFirst);
            const dataFirst = await resFirst.json();

            if (!resFirst.ok) {
                const firstError = dataFirst.errors
                    ? Object.values(dataFirst.errors).flat()[0]
                    : (dataFirst.message || 'Có lỗi xảy ra');
                showToast(firstError, 'error');
                return;
            }

            noteId = dataFirst.note.id;

            // Upload inline images now that we have a noteId
            if (typeof uploadInlineContentImages === 'function') {
                await uploadInlineContentImages(noteId, null);
            }
            const finalContent   = typeof getEditorContent === 'function' ? getEditorContent() : '';
            const hasInlineImages = finalContent !== contentBeforeUpload;
            if (hasInlineImages) {
                const bodyUpdate = new URLSearchParams({ title, content: finalContent });
                labelIds.forEach(id => bodyUpdate.append('label_ids[]', id));
                await apiFetch(`/notes/${noteId}`, 'PUT', bodyUpdate).catch(() => {});
            }

            // Optimistic card (note-page.js overrides prependNoteCard to capture the ID)
            const filesToUpload = [..._pendingFiles];
            const tempThumbs    = filesToUpload.map(f => ({
                id: null, url: URL.createObjectURL(f), thumbnail_url: URL.createObjectURL(f),
            }));
            dataFirst.note.content     = finalContent;
            dataFirst.note.attachments = [...(dataFirst.note.attachments ?? []), ...tempThumbs];
            prependNoteCard(dataFirst.note);

            closeNewNoteModal();

            // Upload thumbnail attachments in background
            if (filesToUpload.length > 0) {
                const uploadResults = await uploadPendingFilesParallel(noteId, filesToUpload, null);
                const cardCol       = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
                if (cardCol && typeof updateCardThumbnail === 'function') {
                    updateCardThumbnail(cardCol, [...uploadResults]);
                }
            }
        }

    } catch {
        showToast('Lỗi kết nối, vui lòng thử lại', 'error');
    }
}
