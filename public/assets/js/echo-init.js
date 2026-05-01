/* ── State ─────────────────────────────────────────── */
let _notifications = [];
let _notificationDropdownOpen = false;

/* ── Initialize Echo ───────────────────────────────── */
function initEcho() {
    if (typeof Pusher === 'undefined' || typeof Echo === 'undefined') {
        console.warn('[Echo] Pusher or Echo not loaded. Skipping initialization.');
        return;
    }

    if (!window.__userId || !window.__pusherKey) {
        console.warn('[Echo] Missing userId or pusherKey. Skipping initialization.', {
            userId: window.__userId,
            pusherKey: window.__pusherKey ? '***' : undefined,
        });
        return;
    }

    // Enable Pusher logging in dev
    Pusher.logToConsole = window.__appDebug || false;

    // Nếu dùng __appUrl (backend domain khác), browser sẽ block cookie → 401.
    const authUrl = window.location.origin + '/broadcasting/auth';

    window.EchoInstance = new Echo({
        broadcaster: 'pusher',
        key: window.__pusherKey,
        cluster: window.__pusherCluster || 'ap1',
        forceTLS: true,
        authEndpoint: authUrl,
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            // Gửi cookie session kèm theo request auth (cần thiết cho cross-origin)
            withCredentials: true,
        }
    });

    console.log('[Echo] Initialised. Auth endpoint:', authUrl, '| Origin:', window.location.origin);

    // Monitor connection state for debugging
    window.EchoInstance.connector.pusher.connection.bind('connected', () => {
        console.log('[Echo] Connected to Pusher.');
    });
    window.EchoInstance.connector.pusher.connection.bind('error', (err) => {
        console.error('[Echo] Pusher connection error:', err);
    });
    window.EchoInstance.connector.pusher.connection.bind('failed', () => {
        console.error('[Echo] Pusher connection failed — all transports unavailable.');
    });

    _subscribeToUserChannel();
}

/* ── Subscribe to private user channel ─────────────── */
function _subscribeToUserChannel() {
    const userId = window.__userId;
    if (!userId) return;

    window.EchoInstance.private(`user.${userId}`)
        .listen('.note.shared', (data) => {
            _handleNoteShared(data);
        })
        .listen('.note.updated', (data) => {
            _handleNoteUpdated(data);
        })
        .listen('.share.permission_changed', (data) => {
            _handlePermissionChanged(data);
        })
        .listen('.share.revoked', (data) => {
            _handleShareRevoked(data);
        })
        .listen('.note.deleted', (data) => {
            _handleNoteDeleted(data);
        });

    console.log(`[Echo] Subscribed to private channel: user.${userId}`);
}

/* ── Event Handlers ────────────────────────────────── */
function _handleNoteDeleted(data) {
    // Show toast notification
    const notification = {
        id: Date.now(),
        type: 'revoke',
        icon: 'delete',
        title: 'Ghi chú đã bị xóa',
        message: `<strong>${data.deleted_by.name}</strong> đã xóa "${_truncate(data.note_title, 30)}"`,
        noteId: data.note_id,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);

    // Remove from shared notes container with fade-out animation
    const sharedCol = document.querySelector(
        `#sharedNotesContainer .fn-shared-note-col[data-note-id="${data.note_id}"]`
    );
    if (sharedCol) {
        sharedCol.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        sharedCol.style.opacity = '0';
        sharedCol.style.transform = 'scale(0.85)';
        setTimeout(() => {
            sharedCol.remove();
            // Hide section header if no shared notes remain
            const container = document.getElementById('sharedNotesContainer');
            const section = document.getElementById('sharedSection');
            if (section && container && container.children.length === 0) {
                section.style.display = 'none';
            }
        }, 380);
    }

    // Close shared modal if it's open for this note
    const sharedModal = document.getElementById('sharedNoteModal');
    if (sharedModal && sharedModal.classList.contains('show') &&
        String(sharedModal.dataset.noteId) === String(data.note_id)) {
        if (typeof closeSharedNoteModal === 'function') closeSharedNoteModal();
    }
}

function _handleNoteShared(data) {
    const notification = {
        id: Date.now(),
        type: 'share',
        icon: 'share',
        title: 'Ghi chú mới được chia sẻ',
        message: `<strong>${data.shared_by.name}</strong> đã chia sẻ "${_truncate(data.note_title, 30)}" với bạn`,
        avatarUrl: data.shared_by.avatar_url,
        noteId: data.note_id,
        time: new Date(),
        unread: true,
    };

    _addNotification(notification);
    _showRealtimeToast(notification);

    // Play subtle notification sound
    _playNotificationSound();

    // Refresh shared notes section in DOM without page reload
    if (typeof refreshSharedNotesSection === 'function') {
        refreshSharedNotesSection();
    }
}

function _handleNoteUpdated(data) {
    const notification = {
        id: Date.now(),
        type: 'update',
        icon: 'edit_note',
        title: 'Ghi chú được cập nhật',
        message: `<strong>${data.updated_by.name}</strong> đã chỉnh sửa "${_truncate(data.note_title, 30)}"`,
        avatarUrl: data.updated_by.avatar_url,
        noteId: data.note_id,
        time: new Date(),
        unread: true,
    };

    _addNotification(notification);
    _showRealtimeToast(notification);

    const excerpt = data.note_excerpt || '';
    const attachments = data.attachments || [];
    const thumbUrl = attachments.length > 0
        ? (attachments[0].thumbnail_url || attachments[0].url)
        : null;

    // ── Update card in DOM (owner view: #notesContainer) ───────────
    const ownerCol = document.querySelector(`#notesContainer .note-col[data-note-id="${data.note_id}"]`);
    if (ownerCol) {
        const titleEl = ownerCol.querySelector('.fn-note-title');
        if (titleEl) titleEl.textContent = data.note_title;
        const excerptEl = ownerCol.querySelector('.fn-note-excerpt');
        if (excerptEl) excerptEl.textContent = excerpt;
        const dateEl = ownerCol.querySelector('.fn-note-date');
        if (dateEl) dateEl.textContent = 'Vừa cập nhật';
        _updateCardThumbnail(ownerCol, thumbUrl);
    }

    // ── Update card in DOM (shared user view: #sharedNotesContainer) ──
    const sharedCol = document.querySelector(`#sharedNotesContainer .fn-shared-note-col[data-note-id="${data.note_id}"]`);
    if (sharedCol) {
        const titleEl = sharedCol.querySelector('.fn-note-title');
        if (titleEl) titleEl.textContent = data.note_title;
        const excerptEl = sharedCol.querySelector('.fn-note-excerpt');
        if (excerptEl) excerptEl.textContent = excerpt;
        const dateEl = sharedCol.querySelector('.fn-note-date');
        if (dateEl) dateEl.textContent = 'Vừa cập nhật';
        _updateCardThumbnail(sharedCol, thumbUrl);
    }

    // ── If the shared note modal is open for this note, update it too ─
    const sharedModal = document.getElementById('sharedNoteModal');
    if (sharedModal && sharedModal.classList.contains('show') &&
        String(sharedModal.dataset.noteId) === String(data.note_id)) {

        // Only update fields that are NOT currently focused (don't stomp user's edits)
        if (data.updated_by.id !== window.__userId) {
            const titleInput = sharedModal.querySelector('.sn-title');
            const contentEl = sharedModal.querySelector('.sn-content');
            if (titleInput && document.activeElement !== titleInput) {
                titleInput.value = data.note_title;
            }
            if (contentEl && document.activeElement !== contentEl) {
                // sn-content is a contenteditable div — use innerHTML not .value
                contentEl.innerHTML = data.note_content || '';
            }
            // Show a subtle "updated by" indicator
            const ownerEl = sharedModal.querySelector('.sn-owner-badge');
            if (ownerEl) {
                const prev = ownerEl.textContent;
                ownerEl.textContent = `✏ ${data.updated_by.name} vừa cập nhật`;
                ownerEl.style.color = 'var(--fn-primary, #004ac6)';
                setTimeout(() => {
                    ownerEl.textContent = prev;
                    ownerEl.style.color = '';
                }, 3000);
            }
        }
    }
}

/**
 * Set or remove the thumbnail image on a note card.
 * @param {Element} col      – .note-col or .fn-shared-note-col element
 * @param {string|null} url  – thumbnail URL, or null to remove
 */
function _updateCardThumbnail(col, url) {
    const card = col.querySelector('.fn-note-card');
    if (!card) return;
    let thumb = card.querySelector('.fn-note-thumb');
    if (url) {
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

function _handlePermissionChanged(data) {
    const permLabel = data.new_permission === 'edit' ? 'chỉnh sửa' : 'chỉ đọc';
    const notification = {
        id: Date.now(),
        type: 'permission',
        icon: 'admin_panel_settings',
        title: 'Quyền truy cập thay đổi',
        message: `<strong>${data.changed_by}</strong> đã đổi quyền của bạn thành <strong>${permLabel}</strong> cho "${_truncate(data.note_title, 30)}"`,
        noteId: data.note_id,
        time: new Date(),
        unread: true,
    };

    _addNotification(notification);
    _showRealtimeToast(notification);
}

function _handleShareRevoked(data) {
    const notification = {
        id: Date.now(),
        type: 'revoke',
        icon: 'person_remove',
        title: 'Quyền truy cập bị thu hồi',
        message: `<strong>${data.revoked_by}</strong> đã thu hồi quyền truy cập "${_truncate(data.note_title, 30)}" của bạn`,
        noteId: data.note_id,
        time: new Date(),
        unread: true,
    };

    _addNotification(notification);
    _showRealtimeToast(notification);

    // Remove the note card from "Shared with me" if visible
    const sharedCard = document.querySelector(`.fn-shared-note[data-note-id="${data.note_id}"]`);
    if (sharedCard) {
        sharedCard.style.transition = 'all 0.3s ease';
        sharedCard.style.opacity = '0';
        sharedCard.style.transform = 'scale(0.9)';
        setTimeout(() => sharedCard.remove(), 300);
    }
}

/* ── Notification Management ───────────────────────── */

function _addNotification(notification) {
    _notifications.unshift(notification);

    // Keep max 50 notifications
    if (_notifications.length > 50) {
        _notifications = _notifications.slice(0, 50);
    }

    _updateNotificationBadge();
    if (_notificationDropdownOpen) {
        _renderNotificationList();
    }
}

function _updateNotificationBadge() {
    const dot = document.getElementById('notificationDot');
    const badge = document.getElementById('notificationBadge');
    const unreadCount = _notifications.filter(n => n.unread).length;

    if (dot) {
        dot.classList.toggle('active', unreadCount > 0);
    }
    if (badge) {
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;

    _notificationDropdownOpen = !_notificationDropdownOpen;
    dropdown.classList.toggle('show', _notificationDropdownOpen);

    if (_notificationDropdownOpen) {
        _renderNotificationList();
    }
}

function closeNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
        _notificationDropdownOpen = false;
    }
}

function clearAllNotifications() {
    _notifications = [];
    _updateNotificationBadge();
    _renderNotificationList();
}

function markAllAsRead() {
    _notifications.forEach(n => n.unread = false);
    _updateNotificationBadge();
    _renderNotificationList();
}

function _renderNotificationList() {
    const list = document.getElementById('notificationList');
    if (!list) return;

    if (_notifications.length === 0) {
        list.innerHTML = `
            <div class="fn-notification-empty">
                <span class="material-symbols-outlined">notifications_off</span>
                <p>Không có thông báo nào</p>
            </div>
        `;
        return;
    }

    list.innerHTML = _notifications.map(n => {
        const typeClass = n.type || 'share';
        const avatarContent = n.avatarUrl
            ? `<img src="${_escHtml(n.avatarUrl)}" alt="">`
            : `<span class="material-symbols-outlined">${n.icon || 'notifications'}</span>`;
        const timeStr = _timeAgo(n.time);

        return `
            <div class="fn-notification-item ${n.unread ? 'unread' : ''}"
                 onclick="_onNotificationClick(${n.id})" data-id="${n.id}">
                <div class="fn-notification-avatar ${typeClass}">
                    ${avatarContent}
                </div>
                <div class="fn-notification-content">
                    <p class="fn-notification-text">${n.message}</p>
                    <div class="fn-notification-time">${timeStr}</div>
                </div>
            </div>
        `;
    }).join('');
}

function _onNotificationClick(id) {
    const notification = _notifications.find(n => n.id === id);
    if (!notification) return;

    notification.unread = false;
    _updateNotificationBadge();
    _renderNotificationList();
    closeNotificationDropdown();

    // If it's a note-related notification, scroll to or highlight the note
    if (notification.noteId) {
        const noteCard = document.querySelector(`.note-col[data-note-id="${notification.noteId}"]`);
        if (noteCard) {
            noteCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            noteCard.style.transition = 'box-shadow 0.3s';
            noteCard.style.boxShadow = '0 0 0 3px rgba(99, 102, 241, 0.3)';
            setTimeout(() => { noteCard.style.boxShadow = ''; }, 2000);
        }
    }
}

/* ── Real-time Toast ───────────────────────────────── */

function _showRealtimeToast(notification) {
    let container = document.getElementById('realtimeToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'realtimeToastContainer';
        container.className = 'fn-realtime-toast';
        document.body.appendChild(container);
    }

    const typeIcons = {
        share: 'share',
        update: 'edit_note',
        permission: 'admin_panel_settings',
        revoke: 'person_remove',
    };

    const toast = document.createElement('div');
    toast.className = 'fn-realtime-toast-item';
    toast.innerHTML = `
        <span class="material-symbols-outlined fn-toast-icon">${typeIcons[notification.type] || 'notifications'}</span>
        <div class="fn-toast-body">
            <p class="fn-toast-title">${notification.title}</p>
            <p class="fn-toast-desc">${_stripHtml(notification.message)}</p>
        </div>
        <button class="fn-toast-close" onclick="this.parentElement.classList.add('exit'); setTimeout(() => this.parentElement.remove(), 300);">
            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
        </button>
    `;

    container.appendChild(toast);

    // Auto-remove after 6 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('exit');
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);
}

/* ── Notification Sound ────────────────────────────── */

function _playNotificationSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);

        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
    } catch (e) {
        // Audio not available — silently skip
    }
}

/* ── Utilities ─────────────────────────────────────── */

function _truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '…' : str;
}

function _escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function _stripHtml(str) {
    const div = document.createElement('div');
    div.innerHTML = str || '';
    return div.textContent;
}

function _timeAgo(date) {
    const now = new Date();
    const diff = Math.floor((now - new Date(date)) / 1000);

    if (diff < 10) return 'Vừa xong';
    if (diff < 60) return `${diff} giây trước`;
    if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
    return `${Math.floor(diff / 86400)} ngày trước`;
}

/* ── Close dropdown on outside click ───────────────── */
document.addEventListener('click', (e) => {
    if (_notificationDropdownOpen) {
        const wrapper = document.getElementById('notificationWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotificationDropdown();
        }
    }
});

/* ── Init on DOM ready ─────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initEcho();
});
