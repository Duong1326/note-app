/**
 * note-attachments.js – Image attachment upload, preview, and lightbox logic.
 * Depends on: app.js (apiFetch, getCsrfToken, escapeHtml, escapeAttr, showToast)
 *             note-cards.js (updateCardThumbnail)
 */

// ═══════════════════════════════════════════════════
// State
// ═══════════════════════════════════════════════════

/** Files pending upload (selected but note not yet saved) */
let _pendingFiles = [];
/** Existing attachments loaded when opening edit mode */
let _existingAttachments = [];

// ═══════════════════════════════════════════════════
// Attachment Section Toggle
// ═══════════════════════════════════════════════════

function toggleAttachmentSection() {
    const section = document.getElementById('attachmentSection');
    const btn = document.getElementById('btnToggleAttachment');
    const isHidden = section.classList.contains('d-none');
    section.classList.toggle('d-none', !isHidden);
    section.classList.toggle('d-flex', isHidden);
    btn.classList.toggle('active', isHidden);
}

function showAttachmentSection() {
    const section = document.getElementById('attachmentSection');
    const btn = document.getElementById('btnToggleAttachment');
    if (section.classList.contains('d-none')) {
        section.classList.remove('d-none');
        section.classList.add('d-flex');
        btn.classList.add('active');
    }
}

// ═══════════════════════════════════════════════════
// Preview Rendering
// ═══════════════════════════════════════════════════

/** Render local previews for pending files (before upload) */
function renderPendingPreviews() {
    const container = document.getElementById('pendingPreviews');
    container.innerHTML = '';
    _pendingFiles.forEach((file, idx) => {
        const url = URL.createObjectURL(file);
        const thumb = document.createElement('div');
        thumb.className = 'fn-attachment-thumb';
        thumb.innerHTML = `
            <img src="${url}" alt="${escapeHtml(file.name)}" onclick="openLightbox('${url}')">
            <button type="button" class="fn-attachment-thumb-remove" title="Remove"
                onclick="removePendingFile(${idx})">&#x2715;</button>`;
        container.appendChild(thumb);
    });
}

function removePendingFile(idx) {
    _pendingFiles.splice(idx, 1);
    renderPendingPreviews();
}

/** Render saved attachments (edit mode only) */
function renderExistingAttachments() {
    const container = document.getElementById('existingAttachments');
    container.innerHTML = '';
    _existingAttachments.forEach(att => {
        const thumb = document.createElement('div');
        thumb.className = 'fn-attachment-thumb';
        thumb.dataset.attachmentId = att.id;
        const fullUrl = escapeAttr(att.url);
        thumb.innerHTML = `
            <img src="${escapeAttr(att.thumbnail_url || att.url)}" alt="attachment" onclick="openLightbox('${fullUrl}')">
            <button type="button" class="fn-attachment-thumb-remove" title="Delete image"
                onclick="removeExistingAttachment(${att.note_id ?? _editingNoteId}, ${att.id}, this)">&#x2715;</button>`;
        container.appendChild(thumb);
    });
}

// ═══════════════════════════════════════════════════
// Lightbox
// ═══════════════════════════════════════════════════

function openLightbox(url) {
    const lb = document.getElementById('imageLightbox');
    const img = document.getElementById('lightboxImage');
    if (!lb || !img) return;
    img.src = url;
    lb.classList.remove('d-none');
    document.body.style.overflow = 'hidden'; // avoid double scrollbar if modal has one
}

function closeLightbox(e) {
    // Only close if we didn't click inside the image itself (if event is passed)
    const lb = document.getElementById('imageLightbox');
    if (!lb) return;
    lb.classList.add('d-none');
    document.getElementById('lightboxImage').src = '';
    // Restore overflow if modal is still open
    if (document.getElementById('newNoteModal')?.classList.contains('show')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// ═══════════════════════════════════════════════════
// AJAX: Delete & Upload
// ═══════════════════════════════════════════════════

async function removeExistingAttachment(noteId, attachmentId, btn) {
    const thumb = btn.closest('.fn-attachment-thumb');
    thumb.classList.add('uploading'); // triggers CSS spinner
    try {
        const res = await apiFetch(`/notes/${noteId}/attachments/${attachmentId}`, 'DELETE');
        if (!res.ok) throw new Error('Failed to delete image');
        _existingAttachments = _existingAttachments.filter(a => a.id !== attachmentId);
        thumb.remove();

        // Dynamically update the note card on the dashboard
        const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
        if (col) {
            updateCardThumbnail(col, _existingAttachments);
            const editBtn = col.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
            if (editBtn) {
                editBtn.dataset.attachments = JSON.stringify(_existingAttachments);
            }
        }
    } catch (err) {
        thumb.classList.remove('uploading');
        showToast(err.message || 'Không thể xóa ảnh', 'error');
    }
}

/** Upload all pending files after the note has been saved */
async function uploadPendingFiles(noteId) {
    const container = document.getElementById('pendingPreviews');
    const thumbs = [...container.querySelectorAll('.fn-attachment-thumb')];
    const results = [];

    for (let i = 0; i < _pendingFiles.length; i++) {
        const file = _pendingFiles[i];
        const thumb = thumbs[i];
        if (thumb) thumb.classList.add('uploading');

        const formData = new FormData();
        formData.append('image', file);
        formData.append('_token', getCsrfToken());

        try {
            const res = await apiFetch(`/notes/${noteId}/attachments`, 'POST', formData);
            const data = await res.json();
            if (data.success) {
                results.push(data.attachment);
                if (thumb) thumb.remove();
            } else {
                showToast(data.message || 'Tải ảnh thất bại', 'error');
                if (thumb) thumb.classList.remove('uploading');
            }
        } catch {
            showToast('Lỗi kết nối khi tải ảnh', 'error');
            if (thumb) thumb.classList.remove('uploading');
        }
    }
    _pendingFiles = [];
    return results;
}
