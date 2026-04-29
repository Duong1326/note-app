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

function openEditNoteModal(btn) {
    _editingNoteId = btn.dataset.id;
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

    // Hide attachment section when images exist (thumbnail is shown above title)
    // Show it only when there are no images so user can add one
    const section = document.getElementById('attachmentSection');
    if (_existingAttachments.length > 0) {
        section?.classList.add('d-none');
        section?.classList.remove('d-flex');
        document.getElementById('btnToggleAttachment')?.classList.remove('active');
    } else {
        section?.classList.add('d-none');
        section?.classList.remove('d-flex');
        document.getElementById('btnToggleAttachment')?.classList.remove('active');
    }

    // Show modal thumbnail preview
    updateModalThumbnail();

    document.querySelector('.fn-modal-title').innerText = 'Chỉnh sửa ghi chú';
    _showModal();
}

function closeNewNoteModal() {
    document.getElementById('newNoteModal')?.classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('createNoteForm')?.reset();

    // One-time unlock: clear token so next access requires re-authentication
    if (_editingNoteId) clearLockToken(_editingNoteId);

    _editingNoteId = null;
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
    const content = getEditorContent();

    if (!title) {
        showToast('Vui lòng nhập tiêu đề ghi chú', 'error');
        document.getElementById('modalNoteTitle').focus();
        return;
    }

    const labelIds = [...document.querySelectorAll('input[name="label_ids[]"]:checked')].map(cb => cb.value);
    const isEditing = _editingNoteId !== null;

    // ── Kiểm tra thay đổi (chỉ áp dụng khi đang edit) ──
    if (isEditing && _originalSnapshot) {
        const currentLabelIds = labelIds.map(String).sort().join(',');
        const hasChanges =
            title !== _originalSnapshot.title ||
            content !== _originalSnapshot.content ||
            currentLabelIds !== _originalSnapshot.labelIds ||
            _pendingFiles.length > 0;                        // có ảnh mới

        if (!hasChanges) {
            // Không có gì thay đổi → đóng modal, không gọi API
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

    const url = isEditing ? `/notes/${_editingNoteId}` : window.FN_STORE_URL;
    const method = isEditing ? 'PUT' : 'POST';

    const body = new URLSearchParams({ title, content });
    labelIds.forEach(id => body.append('label_ids[]', id));

    try {
        const token = isEditing ? getLockToken(_editingNoteId) : null;
        const headers = token ? { 'X-Note-Token': token } : {};
        const res = await apiFetch(url, method, body, headers);
        const data = await res.json();

        if (!res.ok) {
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message || 'Có lỗi xảy ra');
            showToast(firstError, 'error');
            // Restore save button
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = saveBtnOriginalHtml; }
            return;
        }

        const noteId = data.note.id;
        const filesToUpload = [..._pendingFiles];
        const existingAtts = [..._existingAttachments];
        const inlineFiles = [..._inlineContentFiles]; // snapshot before modal close
        const savedContentHtml = getEditorContent();  // snapshot HTML before modal close

        // ── Close modal immediately (optimistic UI) ──
        // Create temporary blob thumbnails for instant visual feedback
        const tempThumbs = filesToUpload.map(f => ({
            id: null,
            url: URL.createObjectURL(f),
            thumbnail_url: URL.createObjectURL(f),
        }));

        if (isEditing) {
            data.note.attachments = [...existingAtts, ...tempThumbs];
            patchNoteCard(_editingNoteId, data.note);
            const col = document.querySelector(`.note-col[data-note-id="${_editingNoteId}"]`);
            moveCardToTopOfUnpinned(col);
        } else {
            data.note.attachments = [...(data.note.attachments ?? []), ...tempThumbs];
            prependNoteCard(data.note);
        }

        closeNewNoteModal();

        // ── Upload pending thumbnail files in background (parallel) ──
        if (filesToUpload.length > 0) {
            const uploadResults = await uploadPendingFilesParallel(noteId, filesToUpload);

            // Update the card with new attachments once uploads finish
            const allAttachments = [...existingAtts, ...uploadResults];
            data.note.attachments = allAttachments;
            const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
            if (col) {
                updateCardThumbnail(col, allAttachments);
                const editBtn = col.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
                if (editBtn) {
                    editBtn.dataset.attachments = JSON.stringify(allAttachments);
                }
            }
        }

        // ── Upload inline content images in background ──
        if (inlineFiles.length > 0) {
            let updatedHtml = savedContentHtml;

            // Upload each inline image and replace blob URL in the HTML string
            const uploads = inlineFiles.map(async (item) => {
                try {
                    const compressed = typeof compressImage === 'function'
                        ? await compressImage(item.file) : item.file;
                    const formData = new FormData();
                    formData.append('image', compressed);
                    formData.append('_token', getCsrfToken());
                    const res = await apiFetch(`/notes/${noteId}/attachments`, 'POST', formData);
                    const resData = await res.json();
                    if (resData.success) {
                        // Replace blob URL with real Cloudinary URL in the HTML string
                        updatedHtml = updatedHtml.replace(item.blobUrl, resData.attachment.url);
                        URL.revokeObjectURL(item.blobUrl);
                    }
                } catch { /* handled silently */ }
            });
            await Promise.all(uploads);

            // Save updated content with real URLs
            const contentBody = new URLSearchParams({ title, content: updatedHtml });
            labelIds.forEach(id => contentBody.append('label_ids[]', id));
            const editToken = getLockToken(noteId);
            const editHeaders = editToken ? { 'X-Note-Token': editToken } : {};
            await apiFetch(`/notes/${noteId}`, 'PUT', contentBody, editHeaders).catch(() => {});

            // Update card content data
            const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
            if (col) {
                const editBtn = col.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
                if (editBtn) editBtn.dataset.content = updatedHtml;
            }
        }

    } catch {
        showToast('Lỗi kết nối, vui lòng thử lại', 'error');
        // Restore save button if modal is still open
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = saveBtnOriginalHtml; }
    }
}
