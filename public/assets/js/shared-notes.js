/**
 * ─────────────────────────────────────────────────────────
 * Shared Notes — View / Edit Modal
 * Modal HTML is in dashboard.blade.php (#sharedNoteModal)
 * ─────────────────────────────────────────────────────────
 */

/* ── Escape HTML helper ─────────────────────────────── */
function _escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}

/* ── Open modal — fetch + populate ─────────────────── */
async function openSharedNoteModal(noteId, permission) {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) {
        console.error('[SharedNote] #sharedNoteModal not found in DOM');
        return;
    }

    // Show modal immediately with loading state
    modal.querySelector('.sn-title').value       = 'Đang tải...';
    modal.querySelector('.sn-content').value     = '';
    modal.querySelector('.sn-owner-badge').textContent = '';
    modal.querySelector('.sn-perm-badge').textContent  = '';
    modal.querySelector('.sn-save-btn').style.display  = 'none';
    modal.classList.add('show');

    try {
        const res  = await fetch(`/notes/${noteId}/shared-view`, {
            headers: {
                'Accept'           : 'application/json',
                'X-Requested-With' : 'XMLHttpRequest',
            },
        });

        const data = await res.json().catch(() => {
            throw new Error(`Server lỗi ${res.status}`);
        });

        if (!res.ok || !data.success) throw new Error(data.message || `Lỗi ${res.status}`);

        const note    = data.note;
        const perm    = data.permission;          // comes from server (not card attr)
        const canEdit = perm === 'edit';

        // ── Fill fields ──────────────────────────────────
        const titleEl   = modal.querySelector('.sn-title');
        const contentEl = modal.querySelector('.sn-content');
        const ownerEl   = modal.querySelector('.sn-owner-badge');
        const permEl    = modal.querySelector('.sn-perm-badge');
        const saveBtn   = modal.querySelector('.sn-save-btn');

        titleEl.value    = note.title   || '';
        contentEl.value  = note.content || '';
        titleEl.readOnly   = !canEdit;
        contentEl.readOnly = !canEdit;
        titleEl.style.opacity   = canEdit ? '1' : '0.7';
        contentEl.style.opacity = canEdit ? '1' : '0.7';

        ownerEl.textContent = note.owner?.name ? `Bởi ${note.owner.name}` : '';
        permEl.textContent  = canEdit ? 'Chỉnh sửa' : 'Chỉ đọc';
        permEl.className    = `fn-perm-badge sn-perm-badge ${perm} ms-1`;

        saveBtn.style.display = canEdit ? 'inline-flex' : 'none';

        // Store for save()
        modal.dataset.noteId     = noteId;
        modal.dataset.permission = perm;

    } catch (err) {
        console.error('[SharedNote] Load error:', err);
        modal.querySelector('.sn-title').value   = '⚠ Không thể tải ghi chú';
        modal.querySelector('.sn-content').value = err.message;
        if (typeof showToast === 'function') showToast('Lỗi: ' + err.message, 'error');
    }
}

/* ── Close modal ────────────────────────────────────── */
function closeSharedNoteModal() {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.dataset.noteId = '';
}

/* ── Keyboard ESC ───────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSharedNoteModal();
});

/* ── Save (edit permission only) ────────────────────── */
async function saveSharedNote() {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) return;

    const noteId  = modal.dataset.noteId;
    const title   = modal.querySelector('.sn-title')?.value.trim();
    const content = modal.querySelector('.sn-content')?.value.trim();
    const saveBtn = modal.querySelector('.sn-save-btn');

    if (!title) {
        if (typeof showToast === 'function') showToast('Tiêu đề không được để trống.', 'error');
        return;
    }

    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Đang lưu...'; }

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const res  = await fetch(`/notes/${noteId}`, {
            method : 'PUT',
            headers: {
                'Content-Type'     : 'application/json',
                'Accept'           : 'application/json',
                'X-CSRF-TOKEN'     : csrf,
                'X-Requested-With' : 'XMLHttpRequest',
            },
            body: JSON.stringify({ title, content }),
        });

        const data = await res.json().catch(() => {
            throw new Error(`Server lỗi ${res.status}`);
        });

        if (!res.ok || !data.success) throw new Error(data.message || `Lỗi ${res.status}`);

        // Update card preview in DOM
        const col = document.querySelector(`.fn-shared-note-col[data-note-id="${noteId}"]`);
        if (col) {
            const t = col.querySelector('.fn-note-title');
            const e = col.querySelector('.fn-note-excerpt');
            if (t) t.textContent = data.note.title;
            if (e) e.textContent = (data.note.content || '').replace(/<[^>]+>/g, '').substring(0, 120);
        }

        if (typeof showToast === 'function') showToast('Đã lưu ghi chú!', 'success');
        closeSharedNoteModal();

    } catch (err) {
        console.error('[SharedNote] Save error:', err);
        if (typeof showToast === 'function') showToast('Lỗi: ' + err.message, 'error');
    } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Lưu'; }
    }
}

/* ── Real-time: refresh shared section after Echo event ─ */
async function refreshSharedNotesSection() {
    if (!window.FN_SHARED_CARDS_URL) return;
    try {
        const res  = await fetch(window.FN_SHARED_CARDS_URL, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (!data.success) return;
        _renderSharedCards(data.shared_notes);
    } catch (err) {
        console.error('[SharedNote] Refresh error:', err);
    }
}

function _renderSharedCards(sharedNotes) {
    const container  = document.getElementById('sharedNotesContainer');
    const section    = document.getElementById('sharedSection');
    const countBadge = document.getElementById('sharedCount');

    if (!container) return;

    if (sharedNotes.length === 0) {
        if (section) section.style.display = 'none';
        return;
    }

    if (section)    section.style.display  = '';
    if (countBadge) countBadge.textContent = sharedNotes.length;

    const existing = new Set(
        [...container.querySelectorAll('[data-note-id]')].map(el => el.dataset.noteId)
    );

    for (const share of sharedNotes) {
        const noteIdStr = String(share.note.id);

        if (existing.has(noteIdStr)) {
            // ── Card already in DOM → patch it in-place instead of skipping ──
            _patchSharedCard(noteIdStr, share.note);
        } else {
            // ── New shared note → insert card ────────────────────────────────
            const col = _buildSharedCard(share);
            container.insertBefore(col, container.firstChild);
            requestAnimationFrame(() => col.classList.add('fn-animate-in'));
        }
    }
}

/**
 * Patch an existing shared note card in the DOM without re-building it.
 * Called both from _renderSharedCards and from the real-time echo handler.
 * @param {string|number} noteId
 * @param {{ title: string, content: string, updated_at: string }} note
 */
function _patchSharedCard(noteId, note) {
    const col = document.querySelector(
        `#sharedNotesContainer .fn-shared-note-col[data-note-id="${noteId}"]`
    );
    if (!col) return;

    const titleEl   = col.querySelector('.fn-note-title');
    const excerptEl = col.querySelector('.fn-note-excerpt');
    const dateEl    = col.querySelector('.fn-note-date');

    if (titleEl)   titleEl.textContent   = note.title || 'Không có tiêu đề';
    if (excerptEl) excerptEl.textContent = (note.content || '').replace(/<[^>]+>/g, '').substring(0, 120);
    if (dateEl)    dateEl.textContent    = note.updated_at || 'Vừa cập nhật';
}

function _buildSharedCard(share) {
    const note      = share.note;
    const perm      = share.permission;
    const permLabel = perm === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc';

    const avatarHtml = note.owner.avatar_url
        ? `<img src="${_escHtml(note.owner.avatar_url)}" alt="">`
        : (note.owner.name || '?').substring(0, 2).toUpperCase();

    const labelsHtml = (note.labels || []).slice(0, 3)
        .map(l => `<span class="badge rounded-pill fn-label-badge">${_escHtml(l.name)}</span>`)
        .join('');

    const excerpt = (note.content || '').replace(/<[^>]+>/g, '').substring(0, 120);

    const col = document.createElement('div');
    col.className              = 'col-12 col-md-6 col-lg-4 col-xl-3 fn-shared-note-col';
    col.dataset.noteId         = note.id;
    col.dataset.shareId        = share.share_id;
    col.dataset.permission     = perm;

    col.innerHTML = `
        <div class="fn-note-card fn-shared-card" style="cursor:pointer"
             onclick="openSharedNoteModal(${note.id}, '${perm}')">
            <div class="fn-shared-owner">
                <div class="fn-shared-owner-avatar">${avatarHtml}</div>
                <span class="fn-shared-owner-name">${_escHtml(note.owner.name)}</span>
                <span class="fn-perm-badge ${perm}">${permLabel}</span>
            </div>
            <div class="fn-note-card-header">
                <h4 class="fn-note-title">${_escHtml(note.title || 'Không có tiêu đề')}</h4>
            </div>
            ${labelsHtml ? `<div class="fn-note-labels">${labelsHtml}</div>` : ''}
            <p class="fn-note-excerpt">${_escHtml(excerpt)}</p>
            <div class="fn-note-meta">
                <span class="fn-note-date">${_escHtml(note.updated_at || '')}</span>
                <span class="material-symbols-outlined fn-share-badge" title="Được chia sẻ">group</span>
            </div>
        </div>`;

    return col;
}
