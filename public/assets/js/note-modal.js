/**
 * note-modal.js – Create / Edit note modal logic and form submission.
 * Depends on: app.js (apiFetch, showToast)
 *             note-cards.js (patchNoteCard, prependNoteCard, moveCardToTopOfUnpinned)
 *             note-attachments.js (_pendingFiles, _existingAttachments, renderExistingAttachments,
 *                                  uploadPendingFiles, showAttachmentSection)
 *             note-lock.js (getLockToken, clearLockToken)
 */

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
    document.getElementById('createNoteForm').reset();
    document.getElementById('existingAttachments').innerHTML = '';
    document.getElementById('pendingPreviews').innerHTML = '';

    // Hide attachment section
    const section = document.getElementById('attachmentSection');
    section.classList.add('d-none');
    section.classList.remove('d-flex');
    document.getElementById('btnToggleAttachment').classList.remove('active');

    _showModal();
    setTimeout(() => document.getElementById('modalNoteTitle').focus(), 350);
}

function openEditNoteModal(btn) {
    _editingNoteId = btn.dataset.id;
    _pendingFiles = [];
    _existingAttachments = JSON.parse(btn.dataset.attachments || '[]');

    const labels = JSON.parse(btn.dataset.labels || '[]');
    document.getElementById('modalNoteTitle').value = btn.dataset.title;
    document.getElementById('modalNoteContent').value = btn.dataset.content ?? '';

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
    document.getElementById('pendingPreviews').innerHTML = '';

    // Show attachment section if there are existing images
    const section = document.getElementById('attachmentSection');
    if (_existingAttachments.length > 0) {
        section.classList.remove('d-none');
        section.classList.add('d-flex');
        document.getElementById('btnToggleAttachment').classList.add('active');
    } else {
        section.classList.add('d-none');
        section.classList.remove('d-flex');
        document.getElementById('btnToggleAttachment').classList.remove('active');
    }

    document.querySelector('.fn-modal-title').innerText = 'Chỉnh sửa ghi chú';
    _showModal();
}

function closeNewNoteModal() {
    document.getElementById('newNoteModal').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('createNoteForm').reset();

    // One-time unlock: clear token so next access requires re-authentication
    if (_editingNoteId) clearLockToken(_editingNoteId);

    _editingNoteId = null;
    _pendingFiles = [];
    _existingAttachments = [];
    _originalSnapshot = null;
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
    const content = document.getElementById('modalNoteContent').value;

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
            return;
        }

        const noteId = data.note.id;
        let uploadedImages = [];

        // Upload any pending files after the note is saved
        if (_pendingFiles.length > 0) {
            uploadedImages = await uploadPendingFiles(noteId);
            data.note.attachments = [...(data.note.attachments ?? []), ...uploadedImages];
        }

        if (isEditing) {
            // Merge remaining existing attachments with newly uploaded ones
            data.note.attachments = [..._existingAttachments, ...uploadedImages];
            patchNoteCard(_editingNoteId, data.note);

            // Re-sort: move the updated card to the top of unpinned notes
            const col = document.querySelector(`.note-col[data-note-id="${_editingNoteId}"]`);
            moveCardToTopOfUnpinned(col);
        } else {
            prependNoteCard(data.note);
        }

        closeNewNoteModal();
    } catch {
        showToast('Lỗi kết nối, vui lòng thử lại', 'error');
    }
}
