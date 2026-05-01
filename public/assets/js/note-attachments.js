/**
 * note-attachments.js – Image attachment upload, preview, and lightbox logic.
 * Depends on: app.js (apiFetch, getCsrfToken, escapeHtml, escapeAttr, showToast)
 *             note-cards.js (updateCardThumbnail)
 *
 * Public API:
 *   toggleAttachmentSection()     – toggle the attachment panel
 *   showAttachmentSection()       – force-show the attachment panel
 *   renderPendingPreviews()       – render local previews for queued files
 *   removePendingFile(idx)        – remove a pending file by index
 *   renderExistingAttachments()   – render saved attachments in edit mode
 *   uploadPendingFilesParallel()  – upload queued files after note is saved
 *   compressImage(file)           – compress an image before upload
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

/**
 * Open the shared image-picker above the title row.
 * Works in both the dashboard modal (#newNoteModal) and the
 * full-page editor (#noteEditPage) contexts.
 */
function toggleAttachmentSection() {
    // Prefer the page editor; fall back to the modal
    const context = document.getElementById('noteEditPage')
        || document.getElementById('newNoteModal');
    const titleRow = context?.querySelector('.fn-title-row');
    if (!titleRow) return;

    openImgPicker({ el: titleRow, placement: 'above' }, (file) => {
        _pendingFiles.push(file);
        renderPendingPreviews();
    });
}

/** @deprecated – kept for compatibility; use toggleAttachmentSection instead */
function showAttachmentSection() {
    toggleAttachmentSection();
}

function hideAttachmentSection() {
    const section = document.getElementById('attachmentSection');
    const btn = document.getElementById('btnToggleAttachment');
    if (section && !section.classList.contains('d-none')) {
        section.classList.add('d-none');
        section.classList.remove('d-flex');
        btn?.classList.remove('active');
    }
}

// ═══════════════════════════════════════════════════
// Preview Rendering
// ═══════════════════════════════════════════════════

/** Render local previews for pending files (before upload) */
function renderPendingPreviews() {
    const container = document.getElementById('pendingPreviews');
    if (!container) return;
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
    // Refresh the modal thumbnail preview
    updateModalThumbnail();

    // Auto-hide attachment section once an image is present (thumbnail is shown above title)
    if (_pendingFiles.length > 0 || _existingAttachments.length > 0) {
        hideAttachmentSection();
    }
}

function removePendingFile(idx) {
    _pendingFiles.splice(idx, 1);
    renderPendingPreviews();
}

/** Render saved attachments (edit mode only) */
function renderExistingAttachments() {
    const container = document.getElementById('existingAttachments');
    if (!container) return;
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
    const lb = document.getElementById('imageLightbox');
    if (!lb) return;
    lb.classList.add('d-none');
    document.getElementById('lightboxImage').src = '';
    // Restore overflow if modal is still open
    if (document.getElementById('newNoteModal')?.classList.contains('show')) {
        document.body.style.overflow = 'hidden';
    } else if (!document.getElementById('noteEditPage')) {
        // Only restore on dashboard (full-page editor never locks scroll)
        document.body.style.overflow = '';
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

        // Refresh the modal thumbnail preview
        updateModalThumbnail();

        // Dynamically update the note card on the dashboard
        const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
        if (col) {
            updateCardThumbnail(col, _existingAttachments);
        }
    } catch (err) {
        thumb.classList.remove('uploading');
        showToast(err.message || 'Không thể xóa ảnh', 'error');
    }
}

// ═══════════════════════════════════════════════════
// Client-Side Image Compression
// ═══════════════════════════════════════════════════

/**
 * Compress an image file using Canvas before uploading.
 * Resizes to max 2048px on longest side, compresses to JPEG 0.8 quality.
 * Returns a new File object with reduced size.
 */
function compressImage(file, maxSize = 2048, quality = 0.8) {
    return new Promise((resolve) => {
        // Skip non-image or GIF (preserve animation)
        if (!file.type.startsWith('image/') || file.type === 'image/gif') {
            return resolve(file);
        }

        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);

            let { width, height } = img;

            // No resize needed if already within limits and file is small
            if (width <= maxSize && height <= maxSize && file.size <= 1024 * 1024) {
                return resolve(file);
            }

            // Scale down proportionally
            if (width > maxSize || height > maxSize) {
                const ratio = Math.min(maxSize / width, maxSize / height);
                width = Math.round(width * ratio);
                height = Math.round(height * ratio);
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob) return resolve(file);
                    const compressed = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    });
                    // Only use compressed version if it's actually smaller
                    resolve(compressed.size < file.size ? compressed : file);
                },
                'image/jpeg',
                quality
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(file); // Fallback to original on error
        };

        img.src = url;
    });
}

/**
 * Upload files in parallel (used for background uploads after modal closes).
 * Accepts an explicit file list so it doesn't depend on _pendingFiles state.
 * Compresses images client-side before uploading for faster transfer.
 */
async function uploadPendingFilesParallel(noteId, files, lockToken = null) {
    const headers = lockToken ? { 'X-Note-Token': lockToken } : {};
    const promises = files.map(async (file) => {
        try {
            const compressed = await compressImage(file);
            const formData = new FormData();
            formData.append('image', compressed);
            formData.append('_token', getCsrfToken());
            const res = await apiFetch(`/notes/${noteId}/attachments`, 'POST', formData, headers);
            const data = await res.json();
            if (data.success) return data.attachment;
            showToast(data.message || 'Tải ảnh thất bại', 'error');
            return null;
        } catch {
            showToast('Lỗi kết nối khi tải ảnh', 'error');
            return null;
        }
    });

    const results = await Promise.all(promises);
    _pendingFiles = [];
    return results.filter(Boolean);
}
