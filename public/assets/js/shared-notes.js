/**
 * shared-notes.js – Shared notes section UI logic.
 * Depends on: app.js (escapeHtml, escapeAttr, showToast, getCsrfToken)
 *             note-lock.js (requireUnlock, isNoteLocked)
 */

// ── Presence & auto-save state ───────────────────────────────────
let _presenceChannel    = null;
let _sharedSaveTimer    = null;
const _SHARED_SAVE_DELAY = 1500;  // ms debounce for shared auto-save

// ── Lock-aware entry point ───────────────────────────────────────
function openSharedNoteOrUnlock(noteId, permission, isLocked) {
    // Navigate to the full-page editor (same as owner editing)
    // The controller already handles shared-user authorization
    window.location.href = `/notes/${noteId}/edit`;
}

// ── Open modal — fetch + populate ───────────────────────────────
/**
 * @param {number}      noteId
 * @param {string}      permission  - 'edit' | 'read'
 * @param {string|null} lockToken   - one-time unlock token; null for read-only or unlocked notes
 */
async function openSharedNoteModal(noteId, permission, lockToken = null) {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) {
        console.error('[SharedNote] #sharedNoteModal not found in DOM');
        return;
    }

    // Show modal immediately with loading state
    modal.querySelector('.sn-title').value = 'Đang tải...';
    modal.querySelector('.sn-content').innerHTML = '';
    modal.querySelector('.sn-owner-badge').textContent = '';
    modal.querySelector('.sn-perm-badge').textContent = '';
    modal.querySelector('.sn-save-btn').style.display = 'none';
    modal.classList.add('show');

    try {
        const res = await apiFetch(`/notes/${noteId}/shared-view`);

        const data = await res.json().catch(() => {
            throw new Error(`Server lỗi ${res.status}`);
        });

        if (!res.ok || !data.success) throw new Error(data.message || `Lỗi ${res.status}`);

        const note = data.note;
        const perm = data.permission;  // comes from server (not card attr)
        const canEdit = perm === 'edit';

        // ── Fill fields ──────────────────────────────────
        const titleEl = modal.querySelector('.sn-title');
        const contentEl = modal.querySelector('.sn-content');
        const ownerEl = modal.querySelector('.sn-owner-badge');
        const permEl = modal.querySelector('.sn-perm-badge');
        const saveBtn = modal.querySelector('.sn-save-btn');

        titleEl.value = note.title || '';
        titleEl.readOnly = !canEdit;
        titleEl.style.opacity = canEdit ? '1' : '0.7';

        // Render HTML rich-text content (images, headings, dividers)
        contentEl.innerHTML = note.content || '';
        contentEl.contentEditable = canEdit ? 'true' : 'false';
        contentEl.style.opacity = canEdit ? '1' : '0.7';
        contentEl.style.cursor = canEdit ? 'text' : 'default';
        contentEl.style.pointerEvents = canEdit ? '' : 'none';

        ownerEl.textContent = note.owner?.name ? `Bởi ${note.owner.name}` : '';
        permEl.textContent = canEdit ? 'Chỉnh sửa' : 'Chỉ đọc';
        permEl.className = `fn-perm-badge sn-perm-badge ${perm} ms-1`;

        saveBtn.style.display = canEdit ? 'inline-flex' : 'none';

        // Store for save()
        modal.dataset.noteId = noteId;
        modal.dataset.permission = perm;
        modal.dataset.lockToken = lockToken || '';  // passed to saveSharedNote()

        // ── Slash menu support for editable shared notes ──
        if (canEdit) {
            // Remove any stale listener before re-attaching (modal can be opened multiple times)
            contentEl.removeEventListener('input', contentEl._snSlashHandler);
            contentEl._snSlashHandler = () => {
                if (_slashMenuVisible) {
                    handleSlashMenuInput();
                } else {
                    const sel = window.getSelection();
                    if (!sel.rangeCount) return;
                    const node = sel.focusNode;
                    if (node && node.nodeType === Node.TEXT_NODE) {
                        const text = node.textContent;
                        const pos = sel.focusOffset;
                        if (pos > 0 && text[pos - 1] === '/') {
                            showSlashMenu(contentEl);
                        }
                    }
                }
            };
            contentEl.addEventListener('input', contentEl._snSlashHandler);
        }

        // ── Presence channel ──────────────────────────────
        _joinPresenceChannel(noteId, modal);

        // ── Auto-save for edit-permission users ───────────
        if (canEdit) {
            _setupSharedAutoSave(modal, noteId, lockToken);
        }

    } catch (err) {
        console.error('[SharedNote] Load error:', err);
        modal.querySelector('.sn-title').value = '⚠ Không thể tải ghi chú';
        modal.querySelector('.sn-content').textContent = err.message;
        showToast('Lỗi: ' + err.message, 'error');
    }
}

// ── Close modal ──────────────────────────────────────────────────
function closeSharedNoteModal() {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) return;

    // Leave presence channel
    if (_presenceChannel && modal.dataset.noteId) {
        window.EchoInstance?.leave('note.' + modal.dataset.noteId);
        _presenceChannel = null;
    }

    // Cancel pending auto-save
    clearTimeout(_sharedSaveTimer);

    modal.classList.remove('show');
    // Reset content to avoid flash of old HTML on next open
    const contentEl = modal.querySelector('.sn-content');
    if (contentEl) {
        contentEl.innerHTML = '';
        contentEl.contentEditable = 'false';
        // Remove stale slash-menu input listener
        if (contentEl._snSlashHandler) {
            contentEl.removeEventListener('input', contentEl._snSlashHandler);
            contentEl._snSlashHandler = null;
        }
        // Remove auto-save listener
        if (contentEl._snAutoSaveHandler) {
            contentEl.removeEventListener('input', contentEl._snAutoSaveHandler);
            contentEl._snAutoSaveHandler = null;
        }
    }
    const titleEl = modal.querySelector('.sn-title');
    if (titleEl?._snAutoSaveHandler) {
        titleEl.removeEventListener('input', titleEl._snAutoSaveHandler);
        titleEl._snAutoSaveHandler = null;
    }

    // Clear presence avatars
    const avatarsEl = document.getElementById('snPresenceAvatars');
    if (avatarsEl) avatarsEl.innerHTML = '';

    modal.dataset.noteId = '';
    modal.dataset.lockToken = '';  // discard one-time token
    // Reset shared editor reference so main note modal gets its own editor back
    if (typeof _activeEditor !== 'undefined' && _activeEditor === contentEl) {
        _activeEditor = null;
    }
}

// ── Keyboard ESC + slash menu navigation ─────────────────────────
document.addEventListener('keydown', e => {
    // Let slash menu handle its own keys first (when triggered from sn-content)
    const isInSharedModal = document.getElementById('sharedNoteModal')?.classList.contains('show');
    if (isInSharedModal && _slashMenuVisible && handleSlashMenuKeydown(e)) return;

    if (e.key === 'Escape') {
        if (_slashMenuVisible) { hideSlashMenu(); return; }
        closeSharedNoteModal();
    }
});

// ── Save (edit permission only) ──────────────────────────────────
async function saveSharedNote() {
    const modal = document.getElementById('sharedNoteModal');
    if (!modal) return;

    const noteId = modal.dataset.noteId;
    const lockToken = modal.dataset.lockToken || null;
    const title = modal.querySelector('.sn-title')?.value.trim();
    const saveBtn = modal.querySelector('.sn-save-btn');

    if (!title) {
        showToast('Tiêu đề không được để trống.', 'error');
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="fn-btn-spinner"></span> Đang lưu...';
    }

    try {
        // Upload any pending inline images (data URLs → Cloudinary URLs) BEFORE reading content
        // This prevents sending huge base64 strings and saves real URLs in the database
        const contentEl = modal.querySelector('.sn-content');
        if (typeof uploadInlineContentImages === 'function') {
            await uploadInlineContentImages(noteId, lockToken);
        }

        // Read content AFTER upload so Cloudinary URLs are in the DOM
        const content = contentEl?.innerHTML.trim();

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(lockToken ? { 'X-Note-Token': lockToken } : {}),
        };

        const res = await fetch(`/notes/${noteId}`, {
            method: 'PUT',
            headers,
            body: JSON.stringify({ title, content }),
        });

        const data = await res.json().catch(() => {
            throw new Error(`Server lỗi ${res.status}`);
        });

        if (!res.ok || !data.success) throw new Error(data.message || `Lỗi ${res.status}`);

        // Update card preview in DOM (strip HTML tags for excerpt)
        const col = document.querySelector(`.fn-shared-note-col[data-note-id="${noteId}"]`);
        if (col) {
            const t = col.querySelector('.fn-note-title');
            const e = col.querySelector('.fn-note-excerpt');
            if (t) t.textContent = data.note.title;
            if (e) e.textContent = contentToExcerpt(data.note.content);
        }

        showToast('Đã lưu ghi chú!', 'success');
        closeSharedNoteModal();

    } catch (err) {
        console.error('[SharedNote] Save error:', err);
        showToast('Lỗi: ' + err.message, 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span> Lưu';
        }
    }
}

// ── Presence channel helpers ─────────────────────────────────────
function _joinPresenceChannel(noteId, modal) {
    if (!window.EchoInstance) return;

    // Leave any stale channel first
    if (_presenceChannel && modal.dataset.noteId && modal.dataset.noteId !== String(noteId)) {
        window.EchoInstance.leave('note.' + modal.dataset.noteId);
    }

    _presenceChannel = window.EchoInstance.join('note.' + noteId)
        .here((members) => {
            _renderPresenceAvatars(members.filter(m => m.id !== window.__userId));
        })
        .joining((member) => {
            if (member.id === window.__userId) return;
            const el = document.getElementById('snPresenceAvatars');
            if (el && !el.querySelector(`[data-uid="${member.id}"]`)) {
                el.insertAdjacentHTML('beforeend', _presenceAvatarHtml(member));
            }
        })
        .leaving((member) => {
            document.querySelector(`#snPresenceAvatars [data-uid="${member.id}"]`)?.remove();
        })
        .error((err) => {
            console.warn('[SharedNote] Presence channel error:', err);
        });
}

function _renderPresenceAvatars(members) {
    const el = document.getElementById('snPresenceAvatars');
    if (!el) return;
    el.innerHTML = members.map(_presenceAvatarHtml).join('');
}

function _presenceAvatarHtml(member) {
    const initials = (member.name || '?').substring(0, 2).toUpperCase();
    const avatar = member.avatar_url
        ? `<img src="${_escapeAttr(member.avatar_url)}" alt="${_escapeAttr(member.name)}">`
        : initials;
    return `<div class="sn-presence-avatar" data-uid="${member.id}" title="${_escapeAttr(member.name)} đang xem">${avatar}</div>`;
}

function _escapeAttr(str) {
    const d = document.createElement('div');
    d.setAttribute('x', str || '');
    return d.outerHTML.slice(4, -2);
}

// ── Auto-save for shared editors ─────────────────────────────────
function _setupSharedAutoSave(modal, noteId, lockToken) {
    const titleEl   = modal.querySelector('.sn-title');
    const contentEl = modal.querySelector('.sn-content');

    const handler = () => {
        clearTimeout(_sharedSaveTimer);
        _sharedSaveTimer = setTimeout(() => _doSharedAutoSave(modal, noteId, lockToken), _SHARED_SAVE_DELAY);
    };

    if (titleEl) {
        titleEl._snAutoSaveHandler = handler;
        titleEl.addEventListener('input', handler);
    }
    if (contentEl) {
        contentEl._snAutoSaveHandler = handler;
        contentEl.addEventListener('input', handler);
    }
}

async function _doSharedAutoSave(modal, noteId, lockToken) {
    const title = modal.querySelector('.sn-title')?.value.trim();
    if (!title) return; // need title to save
    if (!modal.classList.contains('show')) return; // modal closed

    const contentEl = modal.querySelector('.sn-content');
    const content   = contentEl?.innerHTML.trim() || '';

    // Show saving indicator
    const saveBtn = modal.querySelector('.sn-save-btn');
    const origHtml = saveBtn?.innerHTML;
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="fn-btn-spinner"></span>';
    }

    try {
        if (typeof uploadInlineContentImages === 'function') {
            await uploadInlineContentImages(noteId, lockToken);
        }
        const finalContent = contentEl?.innerHTML.trim() || content;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(lockToken ? { 'X-Note-Token': lockToken } : {}),
        };
        const res = await fetch(`/notes/${noteId}`, {
            method: 'PUT',
            headers,
            body: JSON.stringify({ title, content: finalContent }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) return;

        // Patch shared card
        const col = document.querySelector(`.fn-shared-note-col[data-note-id="${noteId}"]`);
        if (col) {
            const t = col.querySelector('.fn-note-title');
            const e = col.querySelector('.fn-note-excerpt');
            const d = col.querySelector('.fn-note-date');
            if (t) t.textContent = data.note?.title || title;
            if (e) e.textContent = contentToExcerpt(finalContent);
            if (d) d.textContent = 'Vừa cập nhật';
        }
    } catch (err) {
        console.error('[SharedNote] Auto-save error:', err);
    } finally {
        if (saveBtn && origHtml) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    }
}

// ── Real-time: refresh shared section after Echo event ───────────
async function refreshSharedNotesSection() {
    if (!window.FN_SHARED_CARDS_URL) return;
    try {
        const res = await apiFetch(window.FN_SHARED_CARDS_URL);
        const data = await res.json();
        if (!data.success) return;
        _renderSharedCards(data.shared_notes);
    } catch (err) {
        console.error('[SharedNote] Refresh error:', err);
    }
}

function _renderSharedCards(sharedNotes) {
    const container = document.getElementById('sharedNotesContainer');
    const section = document.getElementById('sharedSection');
    const countBadge = document.getElementById('sharedCount');

    if (!container) return;

    if (sharedNotes.length === 0) {
        if (section) section.style.display = 'none';
        return;
    }

    if (section) section.style.display = '';
    if (countBadge) countBadge.textContent = sharedNotes.length;

    const existing = new Set(
        [...container.querySelectorAll('[data-note-id]')].map(el => el.dataset.noteId)
    );

    for (const share of sharedNotes) {
        const noteIdStr = String(share.note.id);

        if (existing.has(noteIdStr)) {
            // Card already in DOM → patch it in-place
            _patchSharedCard(noteIdStr, share.note);
        } else {
            // New shared note → insert card
            const col = _buildSharedCard(share);
            container.insertBefore(col, container.firstChild);
            requestAnimationFrame(() => col.classList.add('fn-animate-in'));
        }
    }
}

function _patchSharedCard(noteId, note) {
    const col = document.querySelector(
        `#sharedNotesContainer .fn-shared-note-col[data-note-id="${noteId}"]`
    );
    if (!col) return;

    const titleEl = col.querySelector('.fn-note-title');
    const excerptEl = col.querySelector('.fn-note-excerpt');
    const dateEl = col.querySelector('.fn-note-date');

    if (titleEl) titleEl.textContent = note.title || 'Không có tiêu đề';
    if (excerptEl) excerptEl.textContent = contentToExcerpt(note.content);
    if (dateEl) dateEl.textContent = note.updated_at || 'Vừa cập nhật';

    // Update lock state on card
    col.dataset.locked = note.is_locked ? '1' : '0';
    const card = col.querySelector('.fn-note-card');
    if (card) {
        const perm = col.dataset.permission;
        const locked = note.is_locked;
        card.setAttribute('onclick', `openSharedNoteOrUnlock(${noteId}, '${perm}', ${locked})`);
    }
}

function _buildSharedCard(share) {
    const note = share.note;
    const perm = share.permission;
    const permLabel = perm === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc';
    const isLocked = Boolean(note.is_locked);

    const avatarHtml = note.owner.avatar_url
        ? `<img src="${escapeHtml(note.owner.avatar_url)}" alt="">`
        : (note.owner.name || '?').substring(0, 2).toUpperCase();

    const labelsHtml = (note.labels || []).slice(0, 3)
        .map(l => `<span class="badge rounded-pill fn-label-badge">${escapeHtml(l.name)}</span>`)
        .join('');

    const excerpt = contentToExcerpt(note.content);

    const col = document.createElement('div');
    col.className = 'col-12 col-md-6 col-lg-4 col-xl-3 fn-shared-note-col';
    col.dataset.noteId = note.id;
    col.dataset.shareId = share.share_id;
    col.dataset.permission = perm;
    col.dataset.locked = isLocked ? '1' : '0';

    col.innerHTML = `
        <div class="fn-note-card fn-shared-card" style="cursor:pointer"
             onclick="openSharedNoteOrUnlock(${note.id}, '${perm}', ${isLocked})">
            <div class="fn-shared-owner">
                <div class="fn-shared-owner-avatar">${avatarHtml}</div>
                <span class="fn-shared-owner-name">${escapeHtml(note.owner.name)}</span>
                <span class="fn-perm-badge ${perm}">${permLabel}</span>
            </div>
            <div class="fn-note-card-header">
                <h4 class="fn-note-title">${escapeHtml(note.title || 'Không có tiêu đề')}</h4>
            </div>
            ${labelsHtml ? `<div class="fn-note-labels">${labelsHtml}</div>` : ''}
            <p class="fn-note-excerpt">${escapeHtml(excerpt)}</p>
            <div class="fn-note-meta">
                <span class="fn-note-date">${escapeHtml(note.updated_at || '')}</span>
                <span class="material-symbols-outlined fn-share-badge" title="Được chia sẻ">group</span>
            </div>
        </div>`;

    return col;
}
