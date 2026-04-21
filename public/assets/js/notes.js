/**
 * notes.js – Notes dashboard AJAX logic.
 * Handles: create, update, delete, pin/unpin, view toggle, DOM rendering,
 *          and image attachments via Cloudinary.
 */

// ═══════════════════════════════════════════════════
// 1. API Helpers
// ═══════════════════════════════════════════════════

async function apiFetch(url, method = 'GET', body = null) {
    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
    };
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    return fetch(url, {
        method,
        headers,
        body: body instanceof FormData ? body : (body?.toString() ?? undefined),
    });
}

// ═══════════════════════════════════════════════════
// 2. View Toggle (grid / list)
// ═══════════════════════════════════════════════════

function setNotesView(view) {
    const container = document.getElementById('notesContainer');
    const btnGrid = document.getElementById('btnGridView');
    const btnList = document.getElementById('btnListView');
    if (!container) return;

    const isList = view === 'list';
    container.classList.toggle('fn-view-list', isList);
    btnList?.classList.toggle('active', isList);
    btnGrid?.classList.toggle('active', !isList);
    localStorage.setItem('notesView', view);
}

// ═══════════════════════════════════════════════════
// 3. Note Card DOM Helpers
// ═══════════════════════════════════════════════════

function removeNoteCard(col) {
    col.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    col.style.opacity = '0';
    col.style.transform = 'scale(0.85)';
    col.style.pointerEvents = 'none';
    setTimeout(() => col.remove(), 380);
}

function updateCardLabels(col, labels) {
    let el = col.querySelector('.fn-note-labels');
    if (!labels || labels.length === 0) { el?.remove(); return; }

    const html = labels.slice(0, 3)
        .map(l => `<span class="fn-label-badge" data-label-id="${l.id}">${escapeHtml(l.name)}</span>`)
        .join('');

    if (el) {
        el.innerHTML = html;
    } else {
        const div = document.createElement('div');
        div.className = 'fn-note-labels';
        div.innerHTML = html;
        col.querySelector('.fn-note-card-header').insertAdjacentElement('afterend', div);
    }
}

function updateCardThumbnail(col, attachments) {
    let thumb = col.querySelector('.fn-note-thumb');
    const card = col.querySelector('.fn-note-card');

    if (attachments && attachments.length > 0) {
        const url = attachments[0].thumbnail_url || attachments[0].url;
        if (thumb) {
            thumb.src = url;
        } else {
            const img = document.createElement('img');
            img.className = 'fn-note-thumb';
            img.src = url;
            img.alt = 'Note image';
            card.insertAdjacentElement('afterbegin', img);
        }
    } else if (thumb) {
        thumb.remove();
    }
}

function patchNoteCard(noteId, note) {
    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
    if (!col) return;

    col.querySelector('.fn-note-title').textContent = note.title;
    col.querySelector('.fn-note-excerpt').textContent = (note.content ?? '').replace(/<[^>]*>/g, '').substring(0, 120);
    col.querySelector('.fn-note-date').textContent = note.updated_at ?? 'Just now';

    updateCardLabels(col, note.labels);
    updateCardThumbnail(col, note.attachments);

    const editBtn = col.querySelector('.dropdown-menu li:first-child a');
    if (editBtn) {
        editBtn.dataset.title = note.title;
        editBtn.dataset.content = note.content ?? '';
        editBtn.dataset.labels = JSON.stringify(note.labels?.map(l => l.id) ?? []);
        editBtn.dataset.attachments = JSON.stringify(note.attachments ?? []);
    }
}

function patchPinCard(col, noteId, isPinned) {
    const pinLink = col.querySelector('.dropdown-menu li:nth-child(2) a');
    if (pinLink) {
        const icon = pinLink.querySelector('.material-symbols-outlined');
        icon.style.fontVariationSettings = isPinned ? "'FILL' 1" : '';
        const textNode = [...pinLink.childNodes].find(n => n.nodeType === Node.TEXT_NODE);
        if (textNode) textNode.textContent = isPinned ? ' Unpin' : ' Pin';
        else pinLink.appendChild(document.createTextNode(isPinned ? ' Unpin' : ' Pin'));
        pinLink.setAttribute('onclick', `togglePinAjax(${noteId}, ${isPinned})`);
    }
    const meta = col.querySelector('.fn-note-meta');
    const starEl = meta.querySelector('.fn-pin-star');
    if (isPinned && !starEl) {
        const star = document.createElement('span');
        star.className = 'material-symbols-outlined fn-pin-star';
        star.style.fontVariationSettings = "'FILL' 1";
        star.textContent = 'star';
        meta.appendChild(star);
    } else if (!isPinned && starEl) {
        starEl.remove();
    }
}

function buildNoteCardHtml(note) {
    const labelIds        = JSON.stringify(note.labels?.map(l => l.id) ?? []);
    const attachmentsJson = JSON.stringify(note.attachments ?? []);
    const labelsHtml = (note.labels?.length > 0)
        ? `<div class="fn-note-labels">${note.labels.slice(0, 3).map(l =>
            `<span class="fn-label-badge" data-label-id="${l.id}">${escapeHtml(l.name)}</span>`).join('')}</div>`
        : '';
    const thumbHtml = (note.attachments?.length > 0)
        ? `<img class="fn-note-thumb" src="${escapeAttr(note.attachments[0].thumbnail_url || note.attachments[0].url)}" alt="Note image">`
        : '';
    const pinFill = note.is_pinned ? ` style="font-variation-settings:'FILL' 1;"` : '';
    const pinText = note.is_pinned ? 'Unpin' : 'Pin';
    const pinStar = note.is_pinned
        ? `<span class="material-symbols-outlined fn-pin-star" style="font-variation-settings:'FILL' 1;">star</span>`
        : '';
    const excerpt = (note.content ?? '').replace(/<[^>]*>/g, '').substring(0, 120);

    return `
        <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-note-adding note-col" data-note-id="${note.id}">
            <div class="fn-note-card">
                ${thumbHtml}
                <div class="dropdown fn-note-dropdown">
                    <span class="material-symbols-outlined fn-note-more"
                        data-bs-toggle="dropdown" aria-expanded="false">more_vert</span>
                    <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu shadow-sm border-0 rounded-3">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               data-id="${note.id}"
                               data-title="${escapeAttr(note.title)}"
                               data-content="${escapeAttr(note.content ?? '')}"
                               data-labels='${labelIds}'
                               data-attachments='${escapeAttr(attachmentsJson)}'
                               onclick="openEditNoteModal(this)">
                                <span class="material-symbols-outlined fn-icon-sm">edit</span>
                                Edit
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               onclick="togglePinAjax(${note.id}, ${!!note.is_pinned})">
                                <span class="material-symbols-outlined fn-icon-sm"${pinFill}>push_pin</span>
                                ${pinText}
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger"
                               href="javascript:void(0)"
                               onclick="deleteNoteAjax(${note.id})">
                                <span class="material-symbols-outlined fn-icon-sm">delete</span>
                                Delete
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="fn-note-card-header">
                    <h4 class="fn-note-title">${escapeHtml(note.title)}</h4>
                </div>
                ${labelsHtml}
                <p class="fn-note-excerpt">${escapeHtml(excerpt)}</p>
                <div class="fn-note-meta">
                    <span class="fn-note-date">${note.updated_at ?? 'Just now'}</span>
                    ${pinStar}
                </div>
            </div>
        </div>`;
}

function prependNoteCard(note) {
    let container = document.getElementById('notesContainer');
    if (!container) {
        // Remove empty state and create container
        const emptyState = document.querySelector('.fn-empty-state');
        const wrapper = emptyState?.closest('.col-12');
        emptyState?.remove();
        container = document.createElement('div');
        container.className = 'row g-3';
        container.id = 'notesContainer';
        wrapper?.appendChild(container);
    }
    container.insertAdjacentHTML('afterbegin', buildNoteCardHtml(note));
}

// ═══════════════════════════════════════════════════
// 4. AJAX Actions (delete, pin/unpin)
// ═══════════════════════════════════════════════════

async function deleteNoteAjax(noteId) {
    if (!confirm('Are you sure you want to delete this note?')) return;
    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
    try {
        const res = await apiFetch(`/notes/${noteId}`, 'DELETE');
        if (!res.ok) throw new Error('Delete failed');
        if (col) removeNoteCard(col);
    } catch (err) {
        if (col) { col.style.opacity = ''; col.style.transform = ''; col.style.pointerEvents = ''; }
        showToast(err.message || 'An error occurred', 'error');
    }
}

async function togglePinAjax(noteId, currentlyPinned) {
    const url = currentlyPinned ? `/notes/${noteId}/unpin` : `/notes/${noteId}/pin`;
    try {
        const res = await apiFetch(url, 'POST');
        if (!res.ok) throw new Error('Failed to toggle pin state');
        const { is_pinned: isPinned } = await res.json();
        const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
        if (col) {
            patchPinCard(col, noteId, isPinned);
            moveCardAfterPin(col, isPinned);
        }
    } catch (err) {
        showToast(err.message || 'An error occurred', 'error');
    }
}

/**
 * Smoothly reposition a card after pin/unpin.
 * Pinned cards go to the top; unpinned cards go after the last pinned card.
 */
function moveCardAfterPin(col, isPinned) {
    const container = document.getElementById('notesContainer');
    if (!container) return;

    // Fade out
    col.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
    col.style.opacity = '0';
    col.style.transform = 'scale(0.95)';

    setTimeout(() => {
        if (isPinned) {
            // Move to the very top
            container.prepend(col);
        } else {
            // Move after the last pinned card
            const pinnedCards = container.querySelectorAll('.note-col .fn-pin-star');
            if (pinnedCards.length > 0) {
                const lastPinnedCol = pinnedCards[pinnedCards.length - 1].closest('.note-col');
                lastPinnedCol.insertAdjacentElement('afterend', col);
            } else {
                // No pinned cards left, put at the top
                container.prepend(col);
            }
        }

        // Fade back in
        requestAnimationFrame(() => {
            col.style.opacity = '1';
            col.style.transform = 'scale(1)';
        });
    }, 260);
}

// ═══════════════════════════════════════════════════
// 5. Attachment (Image Upload) Logic
// ═══════════════════════════════════════════════════

/** Files pending upload (selected but note not yet saved) */
let _pendingFiles = [];
/** Existing attachments loaded when opening edit mode */
let _existingAttachments = [];

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

/** Lightbox Actions */
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
        showToast(err.message || 'Could not delete image', 'error');
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
                showToast(data.message || 'Image upload failed', 'error');
                if (thumb) thumb.classList.remove('uploading');
            }
        } catch {
            showToast('Connection error during upload', 'error');
            if (thumb) thumb.classList.remove('uploading');
        }
    }
    _pendingFiles = [];
    return results;
}

// ═══════════════════════════════════════════════════
// 6. Note Modal (Create / Edit)
// ═══════════════════════════════════════════════════

let _editingNoteId = null;

function openNewNoteModal() {
    _editingNoteId = null;
    _pendingFiles = [];
    _existingAttachments = [];

    document.querySelector('.fn-modal-title').innerText = 'New Note';
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

    document.querySelector('.fn-modal-title').innerText = 'Edit Note';
    _showModal();
}

function closeNewNoteModal() {
    document.getElementById('newNoteModal').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('createNoteForm').reset();
    _editingNoteId = null;
    _pendingFiles = [];
    _existingAttachments = [];
}

function _showModal() {
    document.getElementById('newNoteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

async function submitNoteForm() {
    const title = document.getElementById('modalNoteTitle').value.trim();
    const content = document.getElementById('modalNoteContent').value;

    if (!title) {
        showToast('Please enter a note title', 'error');
        document.getElementById('modalNoteTitle').focus();
        return;
    }

    const labelIds = [...document.querySelectorAll('input[name="label_ids[]"]:checked')].map(cb => cb.value);
    const isEditing = _editingNoteId !== null;
    const url = isEditing ? `/notes/${_editingNoteId}` : window.FN_STORE_URL;
    const method = isEditing ? 'PUT' : 'POST';

    const body = new URLSearchParams({ title, content });
    labelIds.forEach(id => body.append('label_ids[]', id));

    try {
        const res = await apiFetch(url, method, body);
        const data = await res.json();

        if (!res.ok) {
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message || 'An error occurred');
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
        } else {
            prependNoteCard(data.note);
        }

        closeNewNoteModal();
    } catch {
        showToast('Connection error, please try again', 'error');
    }
}

// ═══════════════════════════════════════════════════
// 7. Event Bindings
// ═══════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Restore last used view preference
    setNotesView(localStorage.getItem('notesView') || 'grid');

    // Keep dropdown card above sibling cards
    document.addEventListener('shown.bs.dropdown', e => e.target.closest('.note-col')?.classList.add('dropdown-open'));
    document.addEventListener('hidden.bs.dropdown', e => e.target.closest('.note-col')?.classList.remove('dropdown-open'));

    // Note form submit
    document.getElementById('createNoteForm')?.addEventListener('submit', e => {
        e.preventDefault();
        submitNoteForm();
    });

    // Close modal when clicking the overlay backdrop
    document.getElementById('newNoteModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeNewNoteModal();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeNewNoteModal();
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            const modal = document.getElementById('newNoteModal');
            if (modal?.classList.contains('show')) {
                e.preventDefault();
                submitNoteForm();
            }
        }
    });

    // File input change — validate size and queue for upload
    const fileInput = document.getElementById('attachmentFileInput');
    fileInput?.addEventListener('change', () => {
        const files = [...fileInput.files].filter(f => f.size <= 5 * 1024 * 1024);
        if (files.length < fileInput.files.length) {
            showToast('Some images exceeded 5 MB and were skipped', 'error');
        }
        _pendingFiles = [..._pendingFiles, ...files];
        renderPendingPreviews();
        fileInput.value = ''; // reset so the same file can be picked again
    });

    // Drag-and-drop on the drop zone
    const dropzone = document.getElementById('attachmentDropzone');
    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone?.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        const files = [...e.dataTransfer.files]
            .filter(f => f.type.startsWith('image/') && f.size <= 10 * 1024 * 1024);
        _pendingFiles = [..._pendingFiles, ...files];
        renderPendingPreviews();
        showAttachmentSection();
    });

    // Click-to-edit: clicking anywhere on a note card opens the edit modal
    document.addEventListener('click', e => {
        const card = e.target.closest('.fn-note-card');
        if (!card) return;

        // Ignore clicks on interactive elements inside the card
        if (e.target.closest('.dropdown') || e.target.closest('button') || e.target.closest('a')) return;

        const editBtn = card.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
        if (editBtn) openEditNoteModal(editBtn);
    });
});
