/**
 * notes.js – Notes Dashboard AJAX logic.
 * Handles: create, update, delete, pin/unpin, view toggle, DOM rendering.
 * Loaded only on the dashboard page via @push('scripts').
 */

// ═══════════════════════════════════════════════════
// 1. API Helpers
// ═══════════════════════════════════════════════════

/**
 * Send a fetch request with CSRF and JSON Accept headers.
 * @param {string} url
 * @param {string} method
 * @param {URLSearchParams|null} body
 * @returns {Promise<Response>}
 */
async function apiFetch(url, method = 'GET', body = null) {
    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
    };
    if (body) headers['Content-Type'] = 'application/x-www-form-urlencoded';

    return fetch(url, { method, headers, body: body?.toString() ?? undefined });
}

// ═══════════════════════════════════════════════════
// 2. View Toggle
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

/** Fade a column out, then remove it from the DOM. */
function removeNoteCard(col) {
    col.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    col.style.opacity = '0';
    col.style.transform = 'scale(0.85)';
    col.style.pointerEvents = 'none';
    setTimeout(() => col.remove(), 380);
}

/** Update labels section inside a note card. */
function updateCardLabels(col, labels) {
    let el = col.querySelector('.fn-note-labels');

    if (!labels || labels.length === 0) {
        el?.remove();
        return;
    }

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

/** Update an existing note card in-place with fresh data from the API. */
function patchNoteCard(noteId, note) {
    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
    if (!col) return;

    col.querySelector('.fn-note-title').textContent = note.title;
    col.querySelector('.fn-note-excerpt').textContent = (note.content ?? '').replace(/<[^>]*>/g, '').substring(0, 120);
    col.querySelector('.fn-note-date').textContent = note.updated_at ?? 'Vừa xong';

    updateCardLabels(col, note.labels);

    // Keep the edit button's data attributes in sync
    const editBtn = col.querySelector('.dropdown-menu li:first-child a');
    if (editBtn) {
        editBtn.dataset.title = note.title;
        editBtn.dataset.content = note.content ?? '';
        editBtn.dataset.labels = JSON.stringify(note.labels?.map(l => l.id) ?? []);
    }
}

/** Update pin icon + label inside a note card. */
function patchPinCard(col, noteId, isPinned) {
    const pinLink = col.querySelector('.dropdown-menu li:nth-child(2) a');
    if (pinLink) {
        const icon = pinLink.querySelector('.material-symbols-outlined');
        icon.style.fontVariationSettings = isPinned ? "'FILL' 1" : '';

        // Rebuild text node safely (lastChild may be a text node)
        const textNode = [...pinLink.childNodes].find(n => n.nodeType === Node.TEXT_NODE);
        if (textNode) textNode.textContent = isPinned ? ' Bỏ ghim' : ' Ghim';
        else pinLink.appendChild(document.createTextNode(isPinned ? ' Bỏ ghim' : ' Ghim'));

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

/** Build and return HTML string for a new note card. */
function buildNoteCardHtml(note) {
    const labelIds = JSON.stringify(note.labels?.map(l => l.id) ?? []);
    const labelsHtml = (note.labels?.length > 0)
        ? `<div class="fn-note-labels">${note.labels.slice(0, 3).map(l => `<span class="fn-label-badge" data-label-id="${l.id}">${escapeHtml(l.name)}</span>`).join('')}</div>`
        : '';
    const pinStyle = note.is_pinned ? "font-variation-settings:'FILL' 1;" : '';
    const pinText = note.is_pinned ? 'Bỏ ghim' : 'Ghim';
    const pinStar = note.is_pinned
        ? `<span class="material-symbols-outlined fn-pin-star" style="font-variation-settings:'FILL' 1;">star</span>`
        : '';
    const excerpt = (note.content ?? '').replace(/<[^>]*>/g, '').substring(0, 120);

    return `
        <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-note-adding note-col" data-note-id="${note.id}">
            <div class="fn-note-card">
                <div class="dropdown fn-note-dropdown">
                    <span class="material-symbols-outlined fn-note-more"
                        data-bs-toggle="dropdown" aria-expanded="false">more_vert</span>
                    <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               data-id="${note.id}"
                               data-title="${escapeAttr(note.title)}"
                               data-content="${escapeAttr(note.content ?? '')}"
                               data-labels='${labelIds}'
                               onclick="openEditNoteModal(this)">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                                Sửa
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               onclick="togglePinAjax(${note.id}, ${!!note.is_pinned})">
                                <span class="material-symbols-outlined" style="font-size:18px;${pinStyle}">push_pin</span>
                                ${pinText}
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger"
                               href="javascript:void(0)"
                               onclick="deleteNoteAjax(${note.id})">
                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                                Xóa
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
                    <span class="fn-note-date">${note.updated_at ?? 'Vừa xong'}</span>
                    ${pinStar}
                </div>
            </div>
        </div>`;
}

/** Insert a new card, or replace empty-state with a container first. */
function prependNoteCard(note) {
    let container = document.getElementById('notesContainer');

    if (!container) {
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
// 4. AJAX Actions
// ═══════════════════════════════════════════════════

async function deleteNoteAjax(noteId) {
    if (!confirm('Bạn có chắc chắn muốn xóa ghi chú này không?')) return;

    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);

    try {
        const res = await apiFetch(`/notes/${noteId}`, 'DELETE');
        if (!res.ok) throw new Error('Xóa thất bại');

        if (col) removeNoteCard(col);
    } catch (err) {
        if (col) {
            col.style.opacity = '';
            col.style.transform = '';
            col.style.pointerEvents = '';
        }
        showToast(err.message || 'Có lỗi xảy ra', 'error');
    }
}

async function togglePinAjax(noteId, currentlyPinned) {
    const url = currentlyPinned ? `/notes/${noteId}/unpin` : `/notes/${noteId}/pin`;

    try {
        const res = await apiFetch(url, 'POST');
        if (!res.ok) throw new Error('Không thể thay đổi trạng thái ghim');

        const { is_pinned: isPinned } = await res.json();
        const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
        if (col) patchPinCard(col, noteId, isPinned);

    } catch (err) {
        showToast(err.message || 'Có lỗi xảy ra', 'error');
    }
}

// ═══════════════════════════════════════════════════
// 5. Note Modal (Create / Edit)
// ═══════════════════════════════════════════════════

let _editingNoteId = null;

function openNewNoteModal() {
    _editingNoteId = null;
    document.querySelector('.fn-modal-title').innerText = 'New Note';
    document.getElementById('createNoteForm').reset();
    _showModal();
    setTimeout(() => document.getElementById('modalNoteTitle').focus(), 350);
}

function openEditNoteModal(btn) {
    _editingNoteId = btn.dataset.id;
    const labels = JSON.parse(btn.dataset.labels || '[]');

    document.getElementById('modalNoteTitle').value = btn.dataset.title;
    document.getElementById('modalNoteContent').value = btn.dataset.content ?? '';

    document.querySelectorAll('input[name="label_ids[]"]').forEach(cb => {
        cb.checked = labels.includes(parseInt(cb.value));
    });

    document.querySelector('.fn-modal-title').innerText = 'Edit Note';
    _showModal();
}

function closeNewNoteModal() {
    document.getElementById('newNoteModal').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('createNoteForm').reset();
    _editingNoteId = null;
}

function _showModal() {
    document.getElementById('newNoteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

async function submitNoteForm() {
    const title = document.getElementById('modalNoteTitle').value.trim();
    const content = document.getElementById('modalNoteContent').value;

    if (!title) {
        showToast('Vui lòng nhập tiêu đề ghi chú', 'error');
        document.getElementById('modalNoteTitle').focus();
        return;
    }

    const labelIds = [...document.querySelectorAll('input[name="label_ids[]"]:checked')]
        .map(cb => cb.value);

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
                : (data.message || 'Có lỗi xảy ra');
            showToast(firstError, 'error');
            return;
        }

        if (isEditing) {
            patchNoteCard(_editingNoteId, data.note);
        } else {
            prependNoteCard(data.note);
        }

        closeNewNoteModal();
    } catch {
        showToast('Có lỗi kết nối, vui lòng thử lại', 'error');
    }
}

// ═══════════════════════════════════════════════════
// 6. Event Bindings (runs after DOM ready)
// ═══════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // View toggle
    setNotesView(localStorage.getItem('notesView') || 'grid');

    // Dropdown z-index fix
    document.addEventListener('shown.bs.dropdown', e => {
        e.target.closest('.note-col')?.classList.add('dropdown-open');
    });
    document.addEventListener('hidden.bs.dropdown', e => {
        e.target.closest('.note-col')?.classList.remove('dropdown-open');
    });

    // Note form submit
    document.getElementById('createNoteForm')?.addEventListener('submit', e => {
        e.preventDefault();
        submitNoteForm();
    });

    // Close modal on overlay click
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
});
