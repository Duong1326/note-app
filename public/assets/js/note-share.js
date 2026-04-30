/**
 * note-share.js – Share modal UI logic (add recipients, manage permissions).
 * Depends on: app.js (getCsrfToken, escapeHtml, showToast), apiFetch (notes.js)
 */

// ── State ──────────────────────────────────────────
let _shareNoteId = null;
let _emailChips = [];
let _existingShares = [];

// ── Modal open / close ─────────────────────────────
function openShareModal(noteId) {
    _shareNoteId = noteId;
    _emailChips = [];
    _existingShares = [];

    // Reset UI
    renderChips();
    document.getElementById('shareEmailInput').value = '';
    _hideShareError();
    document.getElementById('shareRecipientsSection').classList.add('d-none');
    document.getElementById('shareRecipientList').innerHTML = '';
    document.getElementById('permRead').checked = true;

    // Show modal
    document.getElementById('shareNoteModal').classList.add('show');
    setTimeout(() => document.getElementById('shareEmailInput').focus(), 100);

    // Fetch existing recipients
    fetchShareRecipients(noteId);
}

function closeShareModal() {
    document.getElementById('shareNoteModal').classList.remove('show');
    _shareNoteId = null;
    _emailChips = [];
    _existingShares = [];
}

// ── Event bindings ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Close on overlay click
    document.getElementById('shareNoteModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeShareModal();
    });

    // Keyboard: Escape closes the share modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && _shareNoteId !== null) closeShareModal();
    });

    // Email chip input events
    const emailInput = document.getElementById('shareEmailInput');
    emailInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addChip(this.value.trim());
        } else if (e.key === 'Backspace' && this.value === '' && _emailChips.length > 0) {
            _emailChips.pop();
            renderChips();
        }
    });

    emailInput?.addEventListener('blur', function () {
        if (this.value.trim()) addChip(this.value.trim());
    });
});

// ── Chip input ──────────────────────────────────────
function focusEmailInput() {
    document.getElementById('shareEmailInput').focus();
}

function addChip(email) {
    if (!email) return;
    const cleaned = email.replace(/,/g, '').trim();
    if (!cleaned) return;

    // Avoid duplicates
    if (_emailChips.some(c => c.email === cleaned)) {
        document.getElementById('shareEmailInput').value = '';
        return;
    }

    const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleaned);
    _emailChips.push({ email: cleaned, valid: isValid });
    renderChips();
    document.getElementById('shareEmailInput').value = '';
    _hideShareError();
}

function removeChip(index) {
    _emailChips.splice(index, 1);
    renderChips();
}

function renderChips() {
    const container = document.getElementById('emailChipContainer');
    const input = document.getElementById('shareEmailInput');

    // Remove all existing chips (keep the input)
    container.querySelectorAll('.fn-email-chip').forEach(el => el.remove());

    _emailChips.forEach((chip, i) => {
        const el = document.createElement('span');
        el.className = 'fn-email-chip' + (chip.valid ? '' : ' invalid');
        el.innerHTML =
            escapeHtml(chip.email) +
            `<button type="button" class="fn-email-chip-remove" onclick="removeChip(${i})" tabindex="-1">` +
            `<span class="material-symbols-outlined">close</span></button>`;
        container.insertBefore(el, input);
    });
}

// ── Fetch existing recipients ────────────────────────
async function fetchShareRecipients(noteId) {
    try {
        const res = await apiFetch(`/notes/${noteId}/shares`);
        const data = await res.json();

        if (data.success && data.shares.length > 0) {
            _existingShares = data.shares;
            renderRecipients();
            document.getElementById('shareRecipientsSection').classList.remove('d-none');
        }
    } catch (err) {
        console.error('[NoteShare] fetchShareRecipients error:', err);
    }
}

// ── Submit: add new recipients ───────────────────────
async function submitShareNote(event) {
    event.preventDefault();

    // Finalize any text still in the input
    const rawInput = document.getElementById('shareEmailInput').value.trim();
    if (rawInput) addChip(rawInput);

    if (_emailChips.length === 0) {
        _showShareError('Vui lòng nhập ít nhất một địa chỉ email.');
        return;
    }

    const invalidChips = _emailChips.filter(c => !c.valid);
    if (invalidChips.length > 0) {
        _showShareError(`Email không hợp lệ: ${invalidChips.map(c => c.email).join(', ')}`);
        return;
    }

    const permission = document.querySelector('input[name="sharePermission"]:checked')?.value ?? 'read';
    const emails = _emailChips.map(c => c.email);

    const btn = document.getElementById('shareSubmitBtn');
    _setLoading(btn, true);
    _hideShareError();

    try {
        const res = await apiFetch(`/notes/${_shareNoteId}/shares`, 'POST', null, {
            'Content-Type': 'application/json',
        });

        // Re-send with JSON body (apiFetch doesn't handle JSON body natively)
        const jsonRes = await fetch(`/notes/${_shareNoteId}/shares`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ emails, permission }),
        });

        const data = await jsonRes.json();

        if (!jsonRes.ok) {
            _showShareError(_extractFirstError(data));
            return;
        }

        if (data.success) {
            // Merge new shares into existing list
            data.shares.forEach(s => {
                const idx = _existingShares.findIndex(e => e.user_id === s.user_id);
                if (idx >= 0) _existingShares[idx] = s;
                else _existingShares.push(s);
            });

            renderRecipients();
            if (_existingShares.length > 0) {
                document.getElementById('shareRecipientsSection').classList.remove('d-none');
            }

            // Reset chips
            _emailChips = [];
            renderChips();

            // Toast
            let msg = data.message ?? 'Đã chia sẻ thành công.';
            if (data.skipped && data.skipped.length > 0) {
                msg += ` (Bỏ qua: ${data.skipped.join(', ')} – không thể tự chia sẻ với chính mình.)`;
            }
            showToast(msg, 'success');

            // Update share badge on card
            updateShareBadgeOnCard(_shareNoteId, _existingShares.length > 0);
        }
    } catch (err) {
        console.error('[NoteShare] submitShareNote error:', err);
        _showShareError('Đã xảy ra lỗi kết nối. Vui lòng thử lại.');
    } finally {
        _setLoading(btn, false);
    }
}

// ── Render recipient list ────────────────────────────
function renderRecipients() {
    const list = document.getElementById('shareRecipientList');
    list.innerHTML = '';

    _existingShares.forEach(share => {
        const initials = (share.name || '?').slice(0, 2).toUpperCase();
        const avatarHtml = share.avatar_url
            ? `<img src="${escapeHtml(share.avatar_url)}" alt="${escapeHtml(share.name)}">`
            : initials;

        const sharedAtHtml = share.shared_at
            ? `<div class="fn-share-shared-at">
                   <span class="material-symbols-outlined">schedule</span>
                   Chia sẻ ${escapeHtml(share.shared_at)}
               </div>`
            : '';

        const row = document.createElement('div');
        row.className = 'fn-share-recipient';
        row.dataset.shareId = share.id;
        row.innerHTML = `
            <div class="fn-share-avatar">${avatarHtml}</div>
            <div class="fn-share-recipient-info">
                <div class="fn-share-recipient-name">${escapeHtml(share.name)}</div>
                <div class="fn-share-recipient-email">${escapeHtml(share.email)}</div>
                ${sharedAtHtml}
            </div>
            <select class="fn-share-perm-select" onchange="updateSharePermission(${share.id}, this.value)">
                <option value="read"  ${share.permission === 'read' ? 'selected' : ''}>Chỉ đọc</option>
                <option value="edit"  ${share.permission === 'edit' ? 'selected' : ''}>Chỉnh sửa</option>
            </select>
            <button type="button" class="fn-share-revoke-btn"
                onclick="revokeShare(${share.id})"
                title="Thu hồi quyền truy cập">
                <span class="material-symbols-outlined">person_remove</span>
            </button>
        `;
        list.appendChild(row);
    });
}

// ── Update share permission ──────────────────────────
async function updateSharePermission(shareId, permission) {
    try {
        const res = await fetch(`/notes/${_shareNoteId}/shares/${shareId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ permission }),
        });
        const data = await res.json();

        if (data.success) {
            const share = _existingShares.find(s => s.id === shareId);
            if (share) share.permission = permission;
            showToast('Đã cập nhật quyền chia sẻ.', 'success');
        } else {
            showToast(data.message ?? 'Không thể cập nhật quyền.', 'error');
        }
    } catch (err) {
        console.error('[NoteShare] updateSharePermission error:', err);
        showToast('Đã xảy ra lỗi.', 'error');
    }
}

// ── Revoke share ─────────────────────────────────────
async function revokeShare(shareId) {
    if (!confirm('Thu hồi quyền truy cập của người dùng này?')) return;

    try {
        const res = await apiFetch(`/notes/${_shareNoteId}/shares/${shareId}`, 'DELETE');
        const data = await res.json();

        if (data.success) {
            _existingShares = _existingShares.filter(s => s.id !== shareId);
            renderRecipients();

            if (_existingShares.length === 0) {
                document.getElementById('shareRecipientsSection').classList.add('d-none');
                updateShareBadgeOnCard(_shareNoteId, false);
            }

            showToast('Đã thu hồi quyền truy cập.', 'success');
        } else {
            showToast(data.message ?? 'Không thể thu hồi quyền.', 'error');
        }
    } catch (err) {
        console.error('[NoteShare] revokeShare error:', err);
        showToast('Đã xảy ra lỗi.', 'error');
    }
}

// ── Update share badge on note card DOM ──────────────
function updateShareBadgeOnCard(noteId, hasShares) {
    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
    if (!col) return;

    const meta = col.querySelector('.fn-note-meta .d-flex');
    if (!meta) return;

    const existing = meta.querySelector('.fn-share-badge');

    if (hasShares && !existing) {
        const badge = document.createElement('span');
        badge.className = 'material-symbols-outlined fn-share-badge';
        badge.title = 'Đang chia sẻ';
        badge.textContent = 'group';
        meta.appendChild(badge);
    } else if (!hasShares && existing) {
        existing.remove();
    }
}

// ── Private utility helpers ──────────────────────────
function _showShareError(msg) {
    const el = document.getElementById('shareEmailError');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
}

function _hideShareError() {
    const el = document.getElementById('shareEmailError');
    if (el) el.classList.add('d-none');
}

function _setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    btn.style.opacity = loading ? '0.7' : '1';
}

function _extractFirstError(data) {
    if (data?.message) return data.message;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        return Array.isArray(first) ? first[0] : first;
    }
    return 'Đã xảy ra lỗi.';
}
