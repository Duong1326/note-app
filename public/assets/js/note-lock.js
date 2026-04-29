/**
 * note-lock.js – Note password-lock UI logic.
 *
 * Security model:
 *   - After a correct password, the server issues a short-lived signed HMAC token.
 *   - Tokens live in a JS Map (RAM only – never localStorage).
 *   - Tokens are single-use: cleared after each action so every interaction
 *     requires re-authentication (one-time unlock).
 *   - On page refresh all tokens vanish automatically.
 */

// ─────────────────────────────────────────────────────────────
// Token store
// ─────────────────────────────────────────────────────────────

/** @type {Map<string, {token: string, expiresAt: number}>} */
const _lockTokens = new Map();

/** Normalise a noteId to string (dataset returns strings, inline PHP returns numbers). */
const _id = noteId => String(noteId);

/**
 * Return the stored token for a note if it has > 30 s left, otherwise null.
 */
function getLockToken(noteId) {
    const entry = _lockTokens.get(_id(noteId));
    if (!entry) return null;
    if (Date.now() / 1000 > entry.expiresAt - 30) {
        _lockTokens.delete(_id(noteId));
        return null;
    }
    return entry.token;
}

/**
 * Parse and store the HMAC token returned by the server.
 * Token format: base64("{noteId}|{expiresAt}|{hmac}")
 */
function storeLockToken(noteId, token) {
    try {
        const [, expiresAtStr] = atob(token).split('|');
        _lockTokens.set(_id(noteId), { token, expiresAt: parseInt(expiresAtStr, 10) });
    } catch {
        _lockTokens.set(_id(noteId), { token, expiresAt: Infinity });
    }
}

/** Remove the token for a note (called after each action completes). */
function clearLockToken(noteId) {
    _lockTokens.delete(_id(noteId));
}

// ─────────────────────────────────────────────────────────────
// Lock state helpers
// ─────────────────────────────────────────────────────────────

/** Check whether the DOM card for a note is marked as locked. */
function isNoteLocked(noteId) {
    return document.querySelector(`.note-col[data-note-id="${_id(noteId)}"]`)?.dataset.locked === '1';
}

/**
 * Gate for any mutation on a locked note.
 * Shows the unlock dialog when needed; calls `callback` only on success (or if unlocked).
 */
function requireUnlock(noteId, callback) {
    noteId = _id(noteId);
    if (!isNoteLocked(noteId) || getLockToken(noteId)) {
        callback();
        return;
    }
    openUnlockModal(noteId, callback);
}

// ─────────────────────────────────────────────────────────────
// Modal state
// ─────────────────────────────────────────────────────────────

let _unlockCallback = null;
let _unlockTargetId = null;
let _enableLockTarget = null;
let _changeLockTarget = null;
let _disableLockTarget = null;

// ─────────────────────────────────────────────────────────────
// Generic modal helpers
// ─────────────────────────────────────────────────────────────

function openLockModal(id) {
    document.getElementById(id)?.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeLockModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    document.body.style.overflow = '';
    el.querySelectorAll('input[type="password"]').forEach(i => (i.value = ''));
    el.querySelectorAll('.fn-field-error').forEach(e => {
        e.classList.add('d-none');
        e.textContent = '';
    });
}

function showFieldError(fieldId, message) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
}

function clearFieldError(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    el.textContent = '';
    el.classList.add('d-none');
}

/** Toggle the disabled + text state of a submit button during async requests. */
function setSubmitLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    const textNode = [...btn.childNodes].find(n => n.nodeType === Node.TEXT_NODE);
    if (textNode) textNode.textContent = loading ? ' Đang xử lý…' : textNode.textContent.replace('Đang xử lý…', '').trimStart() || ' ';
}

/** Toggle password field visibility (eye button). */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    const icon = btn.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = isHidden ? 'visibility_off' : 'visibility';
}

function _focusFirst(fieldId) {
    setTimeout(() => document.getElementById(fieldId)?.focus(), 200);
}

// ─────────────────────────────────────────────────────────────
// 1. Unlock modal
// ─────────────────────────────────────────────────────────────

function openUnlockModal(noteId, callback) {
    _unlockTargetId = noteId;
    _unlockCallback = callback;
    clearFieldError('unlockPasswordError');
    openLockModal('unlockNoteModal');
    _focusFirst('unlockPassword');
}

async function submitUnlock(event) {
    event.preventDefault();
    clearFieldError('unlockPasswordError');

    const password = document.getElementById('unlockPassword').value.trim();
    if (!password) {
        showFieldError('unlockPasswordError', 'Vui lòng nhập mật khẩu.');
        return;
    }

    setSubmitLoading('unlockSubmitBtn', true);
    try {
        const res = await apiFetch(`/notes/${_unlockTargetId}/lock/verify`, 'POST', new URLSearchParams({ password }));
        const data = await res.json();

        if (!res.ok || !data.success) {
            showFieldError('unlockPasswordError', data.message || 'Mật khẩu không đúng.');
            return;
        }

        storeLockToken(_unlockTargetId, data.token);
        closeLockModal('unlockNoteModal');
        showToast('Đã mở khoá ghi chú', 'success');
        _unlockCallback?.();
    } catch {
        showFieldError('unlockPasswordError', 'Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        setSubmitLoading('unlockSubmitBtn', false);
    }
}

// ─────────────────────────────────────────────────────────────
// 2. Enable lock modal
// ─────────────────────────────────────────────────────────────

function openEnableLockModal(noteId) {
    _enableLockTarget = noteId;
    ['enableLockPasswordError', 'enableLockConfirmError'].forEach(clearFieldError);
    openLockModal('enableLockModal');
    _focusFirst('enableLockPassword');
}

async function submitEnableLock(event) {
    event.preventDefault();
    ['enableLockPasswordError', 'enableLockConfirmError'].forEach(clearFieldError);

    const password = document.getElementById('enableLockPassword').value;
    const confirm = document.getElementById('enableLockConfirm').value;
    let valid = true;

    if (!password || password.length < 6) {
        showFieldError('enableLockPasswordError', 'Mật khẩu phải có ít nhất 6 ký tự.');
        valid = false;
    }
    if (password !== confirm) {
        showFieldError('enableLockConfirmError', 'Mật khẩu xác nhận không khớp.');
        valid = false;
    }
    if (!valid) return;

    setSubmitLoading('enableLockSubmitBtn', true);
    try {
        const res = await apiFetch(`/notes/${_enableLockTarget}/lock/enable`, 'POST',
            new URLSearchParams({ password, password_confirmation: confirm }));
        const data = await res.json();

        if (!res.ok || !data.success) {
            showFieldError('enableLockPasswordError', data.message || 'Có lỗi xảy ra.');
            return;
        }

        const col = document.querySelector(`.note-col[data-note-id="${_enableLockTarget}"]`);
        if (col) {
            col.dataset.locked = '1';
            patchLockBadge(col, true);
            patchLockDropdown(col, _enableLockTarget, true);
        }

        closeLockModal('enableLockModal');
        showToast('Đã khoá ghi chú thành công', 'success');
    } catch {
        showFieldError('enableLockPasswordError', 'Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        setSubmitLoading('enableLockSubmitBtn', false);
    }
}

// ─────────────────────────────────────────────────────────────
// 3. Change password modal
// ─────────────────────────────────────────────────────────────

function openChangeLockModal(noteId) {
    _changeLockTarget = noteId;
    ['changeLockCurrentError', 'changeLockNewError', 'changeLockConfirmError'].forEach(clearFieldError);
    ['changeLockCurrent', 'changeLockNew', 'changeLockConfirm'].forEach(id => {
        document.getElementById(id).value = '';
    });
    openLockModal('changeLockModal');
    _focusFirst('changeLockCurrent');
}

async function submitChangeLockPassword(event) {
    event.preventDefault();
    ['changeLockCurrentError', 'changeLockNewError', 'changeLockConfirmError'].forEach(clearFieldError);

    const currentPassword = document.getElementById('changeLockCurrent').value;
    const newPassword = document.getElementById('changeLockNew').value;
    const confirmPassword = document.getElementById('changeLockConfirm').value;
    let valid = true;

    if (!currentPassword) {
        showFieldError('changeLockCurrentError', 'Vui lòng nhập mật khẩu hiện tại.');
        valid = false;
    }
    if (!newPassword || newPassword.length < 6) {
        showFieldError('changeLockNewError', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
        valid = false;
    }
    if (newPassword !== confirmPassword) {
        showFieldError('changeLockConfirmError', 'Mật khẩu xác nhận không khớp.');
        valid = false;
    }
    if (!valid) return;

    setSubmitLoading('changeLockSubmitBtn', true);
    try {
        const res = await apiFetch(`/notes/${_changeLockTarget}/lock/password`, 'PUT',
            new URLSearchParams({ current_password: currentPassword, password: newPassword, password_confirmation: confirmPassword }));
        const data = await res.json();

        if (!res.ok || !data.success) {
            showFieldError('changeLockCurrentError', data.message || 'Có lỗi xảy ra.');
            return;
        }

        if (data.token) storeLockToken(_changeLockTarget, data.token);
        closeLockModal('changeLockModal');
        showToast('Đã đổi mật khẩu khoá thành công.', 'success');
    } catch {
        showFieldError('changeLockCurrentError', 'Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        setSubmitLoading('changeLockSubmitBtn', false);
    }
}

// ─────────────────────────────────────────────────────────────
// 4. Disable lock modal
// ─────────────────────────────────────────────────────────────

function openDisableLockModal(noteId) {
    _disableLockTarget = noteId;
    clearFieldError('disableLockPasswordError');
    openLockModal('disableLockModal');
    _focusFirst('disableLockPassword');
}

async function submitDisableLock(event) {
    event.preventDefault();
    clearFieldError('disableLockPasswordError');

    const password = document.getElementById('disableLockPassword').value;
    if (!password) {
        showFieldError('disableLockPasswordError', 'Vui lòng nhập mật khẩu.');
        return;
    }

    setSubmitLoading('disableLockSubmitBtn', true);
    try {
        const res = await apiFetch(`/notes/${_disableLockTarget}/lock`, 'DELETE', new URLSearchParams({ password }));
        const data = await res.json();

        if (!res.ok || !data.success) {
            showFieldError('disableLockPasswordError', data.message || 'Mật khẩu không đúng.');
            return;
        }

        clearLockToken(_disableLockTarget);

        const col = document.querySelector(`.note-col[data-note-id="${_disableLockTarget}"]`);
        if (col) {
            delete col.dataset.locked;
            patchLockBadge(col, false);
            patchLockDropdown(col, _disableLockTarget, false);
        }

        closeLockModal('disableLockModal');
        showToast('Đã gỡ khoá ghi chú.', 'success');
    } catch {
        showFieldError('disableLockPasswordError', 'Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        setSubmitLoading('disableLockSubmitBtn', false);
    }
}

// ─────────────────────────────────────────────────────────────
// DOM patching after lock-state changes
// ─────────────────────────────────────────────────────────────

function patchLockBadge(col, isLocked) {
    const iconWrap = col.querySelector('.fn-note-meta .d-flex.align-items-center');
    if (!iconWrap) return;

    const existing = iconWrap.querySelector('.fn-lock-badge');
    if (isLocked && !existing) {
        const span = document.createElement('span');
        span.className = 'material-symbols-outlined fn-lock-badge';
        span.title = 'Ghi chú đã khoá';
        span.textContent = 'lock';
        iconWrap.appendChild(span);
    } else if (!isLocked && existing) {
        existing.remove();
    }
}

function patchLockDropdown(col, noteId, isLocked) {
    const menu = col.querySelector('.dropdown-menu');
    if (!menu) return;

    // Remove previous lock section (both static Blade-rendered and dynamically added)
    menu.querySelectorAll('.fn-lock-menu-item, .fn-lock-divider').forEach(el => el.remove());

    const divider = `<li class="fn-lock-divider"><hr class="dropdown-divider"></li>`;
    const lockedItems = `
        <li class="fn-lock-menu-item">
            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
               href="javascript:void(0)" onclick="openChangeLockModal(${noteId})">
                <span class="material-symbols-outlined fn-icon-sm">key</span>
                Đổi mật khẩu khoá
            </a>
        </li>
        <li class="fn-lock-menu-item">
            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-warning"
               href="javascript:void(0)" onclick="openDisableLockModal(${noteId})">
                <span class="material-symbols-outlined fn-icon-sm">no_encryption</span>
                Gỡ khoá
            </a>
        </li>`;
    const unlockedItem = `
        <li class="fn-lock-menu-item">
            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
               href="javascript:void(0)" onclick="openEnableLockModal(${noteId})">
                <span class="material-symbols-outlined fn-icon-sm">lock</span>
                Khoá bằng mật khẩu
            </a>
        </li>`;

    menu.insertAdjacentHTML('beforeend', divider + (isLocked ? lockedItems : unlockedItem));
}

// ─────────────────────────────────────────────────────────────
// Keyboard & backdrop dismissal
// ─────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const MODAL_IDS = ['unlockNoteModal', 'enableLockModal', 'changeLockModal', 'disableLockModal'];

    // Close on backdrop click
    MODAL_IDS.forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => {
            if (e.target === e.currentTarget) closeLockModal(id);
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        MODAL_IDS.forEach(id => {
            if (document.getElementById(id)?.classList.contains('show')) closeLockModal(id);
        });
    });
});

// ─────────────────────────────────────────────────────────────
// Expose to global scope (called from inline HTML onclick attrs)
// ─────────────────────────────────────────────────────────────

Object.assign(window, {
    // Token API (used by notes.js)
    getLockToken,
    clearLockToken,
    requireUnlock,
    isNoteLocked,

    // Modal openers (used by inline onclick in Blade / buildNoteCardHtml)
    openEnableLockModal,
    openChangeLockModal,
    openDisableLockModal,

    // Form submit handlers (used by onsubmit in Blade)
    submitUnlock,
    submitEnableLock,
    submitChangeLockPassword,
    submitDisableLock,

    // Generic helpers (used by onsubmit + onclick in Blade)
    closeLockModal,
    togglePasswordVisibility,
});
