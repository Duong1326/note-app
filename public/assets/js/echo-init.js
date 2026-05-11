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

    // Run any post-init hooks registered by other scripts (e.g. app.blade.php)
    if (Array.isArray(window.__echoPostInitHooks)) {
        window.__echoPostInitHooks.forEach(fn => {
            try { fn(window.EchoInstance); } catch (e) {
                console.warn('[Echo] postInitHook error:', e);
            }
        });
    }
}

/* ── Subscribe to private user channel ─────────────── */
function _subscribeToUserChannel() {
    const userId = window.__userId;
    if (!userId) return;

    window.EchoInstance.private(`user.${userId}`)
        .listen('.note.created', (data) => {
            _handleNoteCreated(data);
        })
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
        })
        .listen('.workspace.share_permission_changed', (data) => {
            _handleWsPermissionChanged(data);
        })
        .listen('.workspace.shared', (data) => {
            _handleWorkspaceShared(data);
        })
        .listen('.workspace.deleted', (data) => {
            _handleWorkspaceDeleted(data);
        })
        .listen('.workspace.share_revoked', (data) => {
            _handleWorkspaceShareRevoked(data);
        });

    console.log(`[Echo] Subscribed to private channel: user.${userId}`);
}

/* ── Event Handlers ────────────────────────────────── */
function _handleNoteCreated(data) {
    const note       = data.note;
    const wsId       = data.workspace_id;
    const createdBy  = data.created_by;

    // Only inject card when the user is on the dashboard for the SAME workspace
    const container = document.getElementById('notesContainer');
    if (!container) return;
    if (window.__activeWorkspaceId && String(window.__activeWorkspaceId) !== String(wsId)) return;

    // Toast notification
    const notification = {
        id: Date.now(),
        type: 'share',
        icon: 'note_add',
        title: 'Ghi chú mới',
        message: `<strong>${createdBy.name}</strong> đã tạo "${_truncate(note.title || 'Không có tiêu đề', 30)}"`,
        avatarUrl: createdBy.avatar_url,
        noteId: note.id,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);

    // Inject card into DOM (skip if card already exists)
    if (container.querySelector(`[data-note-id="${note.id}"]`)) return;

    if (typeof buildNoteCardHtml === 'function') {
        const tmp = document.createElement('div');
        tmp.innerHTML = buildNoteCardHtml(note);
        const col = tmp.firstElementChild;
        if (col) {
            col.classList.add('fn-animate-in');
            // Insert after pinned cards (pinned notes always come first)
            const firstUnpinned = container.querySelector('.note-col:not([data-pinned])') ||
                                  container.querySelector('.note-col');
            if (firstUnpinned) {
                container.insertBefore(col, firstUnpinned);
            } else {
                container.insertAdjacentElement('afterbegin', col);
            }
        }
    }
}

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

    // Remove from main workspace notes container (owner/member dashboard)
    const ownerCol = document.querySelector(
        `#notesContainer .note-col[data-note-id="${data.note_id}"]`
    );
    if (ownerCol) {
        ownerCol.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        ownerCol.style.opacity = '0';
        ownerCol.style.transform = 'scale(0.85)';
        setTimeout(() => ownerCol.remove(), 380);
    }

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

    // ── Gọi tất cả hook đã được đăng ký (vd: note-page.js cần update editor) ──
    if (Array.isArray(window.__onNoteUpdatedHooks)) {
        window.__onNoteUpdatedHooks.forEach(fn => {
            try { fn(data); } catch (e) { console.warn('[Echo] onNoteUpdated hook error:', e); }
        });
    }

    const excerpt = data.note_excerpt || '';
    const attachments = data.attachments || [];
    const thumbUrl = attachments.length > 0
        ? (attachments[0].thumbnail_url || attachments[0].url)
        : null;

    // ── Update card in DOM (owner/member workspace view: #notesContainer) ───
    // Only update if the user is currently viewing the same workspace
    const activeWsId = window.__activeWorkspaceId;
    const noteWsId   = data.workspace_id;
    const sameWorkspace = !activeWsId || !noteWsId || String(activeWsId) === String(noteWsId);

    if (sameWorkspace) {
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
    // Note-level share permission change
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

/**
 * Workspace-level share permission changed.
 * Updates: sidebar permission badge, "New Note" button visibility,
 * and the page header meta text — all without a full reload.
 */
function _handleWsPermissionChanged(data) {
    const wsId      = parseInt(data.workspace_id);
    const newPerm   = data.new_permission; // 'edit' | 'read'
    const permLabel = newPerm === 'edit' ? 'Sửa' : 'Đọc';
    const permText  = newPerm === 'edit' ? 'chỉnh sửa' : 'chỉ đọc';

    // 1. Toast & notification
    const notification = {
        id: Date.now(),
        type: 'permission',
        icon: 'admin_panel_settings',
        title: 'Quyền workspace thay đổi',
        message: `<strong>${data.changed_by}</strong> đã đổi quyền của bạn thành <strong>${permText}</strong> trong workspace "${_truncate(data.workspace_name, 30)}"`,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);

    // Only update UI if this is the currently active workspace
    if (parseInt(window.__activeWorkspaceId) !== wsId) return;

    // 2. Update permission badge in sidebar
    const sidebarItem = document.querySelector(`.fn-ws-item[data-ws-id="${wsId}"] .fn-ws-perm-badge`);
    if (sidebarItem) sidebarItem.textContent = permLabel;

    // 3. Show/hide the "New Note" button based on new permission
    const newNoteBtn = document.querySelector('.fn-btn-new-note');
    if (newNoteBtn) {
        newNoteBtn.style.display = newPerm === 'edit' ? '' : 'none';
    }

    // 4. Update the dashboard header meta (e.g. "Chỉnh sửa" vs "Chỉ đọc")
    const pageMeta = document.querySelector('.fn-ws-page-meta');
    if (pageMeta) {
        pageMeta.textContent = newPerm === 'edit' ? 'Quyền chỉnh sửa' : 'Quyền chỉ đọc';
    }
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

/* ── Workspace Real-time Handlers ──────────────────── */

/**
 * Called when the current user is granted access to a new workspace.
 * Shows a notification modal and adds the workspace to the sidebar.
 */
function _handleWorkspaceShared(data) {
    const wsName = data.workspace_name || 'Workspace';
    const sharedBy = data.shared_by || {};
    const permText  = data.permission === 'edit' ? 'chỉnh sửa' : 'chỉ đọc';
    const permLabel = data.permission === 'edit' ? 'Sửa' : 'Đọc';

    // 1. Notification entry
    const notification = {
        id: Date.now(),
        type: 'share',
        icon: 'folder_shared',
        title: 'Workspace mới được chia sẻ',
        message: `<strong>${sharedBy.name}</strong> đã chia sẻ workspace "${_truncate(wsName, 30)}" với bạn (${permText})`,
        avatarUrl: sharedBy.avatar_url,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);
    _playNotificationSound();

    // 2. Inject workspace item into sidebar #wsSharedList
    _injectSharedWorkspaceSidebarItem(data);

    // 3. Show a dedicated modal so the user can immediately switch to it
    _showWorkspaceSharedModal(data);
}

/**
 * Inject a new shared workspace item into the sidebar dropdown.
 */
function _injectSharedWorkspaceSidebarItem(data) {
    const wsId    = data.workspace_id;
    const wsName  = data.workspace_name;
    const perm    = data.permission;
    const permLbl = perm === 'edit' ? 'Sửa' : 'Đọc';
    const isLocked = data.workspace && data.workspace.is_locked ? '1' : '0';

    // Don't duplicate
    if (document.querySelector(`.fn-ws-item[data-ws-id="${wsId}"]`)) return;

    let list = document.getElementById('wsSharedList');
    if (!list) {
        // The shared section might not exist yet — create the separator + container
        const switcher = document.getElementById('workspaceSwitcher');
        const wsDropdown = document.getElementById('wsDropdown');
        if (!wsDropdown) return;

        // Find the shared-with-me item to insert before it
        const sharedMeItem = wsDropdown.querySelector('.fn-ws-shared-me-item');
        if (sharedMeItem) {
            const sep = document.createElement('hr');
            sep.className = 'fn-ws-sep';
            sep.style.cssText = 'margin:0.25rem 0;';
            wsDropdown.insertBefore(sep, sharedMeItem);

            list = document.createElement('div');
            list.id = 'wsSharedList';
            wsDropdown.insertBefore(list, sharedMeItem);
        }
    }
    if (!list) return;

    const el = document.createElement('div');
    el.className = 'fn-ws-item fn-ws-shared fn-animate-in';
    el.dataset.wsId = wsId;
    el.dataset.wsName = wsName;
    el.dataset.wsLocked = isLocked;
    el.innerHTML =
        `<div class="fn-ws-item-info" onclick="switchWorkspace(${wsId}, '${wsName.replace(/'/g, "\\'")}')">` +
            `<span class="fn-ws-item-name">${_escHtml(wsName)}</span>` +
            `<span class="fn-ws-perm-badge">${_escHtml(permLbl)}</span>` +
        `</div>`;
    list.appendChild(el);
}

/**
 * Show a modal informing the user they have been added to a workspace.
 */
function _showWorkspaceSharedModal(data) {
    const wsId   = data.workspace_id;
    const wsName = data.workspace_name || 'Workspace';
    const perm   = data.permission === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc';
    const sharedBy = data.shared_by || {};

    // Remove any pre-existing instance
    const existing = document.getElementById('wsSharedNotifyModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'wsSharedNotifyModal';
    overlay.className = 'fn-modal-overlay show';
    overlay.style.cssText = 'z-index:9999;';
    overlay.innerHTML = `
        <div class="fn-modal-card" style="max-width:420px;">
            <div class="fn-modal-header" style="border-bottom:1px solid var(--fn-outline-variant);">
                <div class="d-flex align-items-center gap-2">
                    <div class="fn-modal-icon" style="background:var(--fn-primary-container);">
                        <span class="material-symbols-outlined" style="color:var(--fn-primary);">folder_shared</span>
                    </div>
                    <div>
                        <h2 class="fn-modal-title">Workspace mới!</h2>
                        <small style="font-size:12px;color:var(--fn-on-surface-variant);">Được chia sẻ bởi ${_escHtml(sharedBy.name || '')}</small>
                    </div>
                </div>
                <button type="button" class="fn-modal-close" onclick="document.getElementById('wsSharedNotifyModal').remove(); document.body.style.overflow='';">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="fn-modal-body" style="padding:1.5rem;">
                <p style="margin:0 0 0.75rem;font-size:0.9375rem;">
                    <strong>${_escHtml(sharedBy.name || '')}</strong> đã chia sẻ workspace
                    <strong>"${_escHtml(wsName)}"</strong> với bạn.
                </p>
                <p style="margin:0;font-size:0.875rem;color:var(--fn-on-surface-variant);">Quyền của bạn: <strong>${_escHtml(perm)}</strong></p>
            </div>
            <div class="fn-modal-footer" style="padding:1rem 1.5rem;">
                <div class="fn-modal-actions">
                    <button type="button" class="fn-modal-btn-cancel"
                        onclick="document.getElementById('wsSharedNotifyModal').remove(); document.body.style.overflow='';">Để sau</button>
                    <button type="button" class="fn-modal-btn-save"
                        onclick="document.getElementById('wsSharedNotifyModal').remove(); document.body.style.overflow=''; switchWorkspace(${wsId}, '${wsName.replace(/'/g, "\\'")}')">Truy cập ngay</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

/**
 * Called when the current user's shared workspace is deleted by the owner.
 * If they are currently inside that workspace, redirect them out.
 */
function _handleWorkspaceDeleted(data) {
    const wsId   = parseInt(data.workspace_id);
    const wsName = data.workspace_name || 'Workspace';

    // 1. Notification
    const notification = {
        id: Date.now(),
        type: 'revoke',
        icon: 'folder_delete',
        title: 'Workspace đã bị xóa',
        message: `<strong>${data.deleted_by.name}</strong> đã xóa workspace "${_truncate(wsName, 30)}"`,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);

    // 2. Remove sidebar item
    const sidebarItem = document.querySelector(`.fn-ws-item[data-ws-id="${wsId}"]`);
    if (sidebarItem) sidebarItem.remove();

    // 3. If currently inside the deleted workspace, force redirect to personal dashboard
    if (parseInt(window.__activeWorkspaceId) === wsId) {
        // Show a blocking notice before redirect
        if (typeof showToast === 'function') {
            showToast(`Workspace "${_truncate(wsName, 30)}" đã bị xóa. Đang chuyển về workspace cá nhân...`, 'warning');
        }
        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 2000);
    }
}

/**
 * Called when the current user's access to a workspace is revoked by the owner.
 * Removes the workspace from the sidebar and redirects if currently inside it.
 */
function _handleWorkspaceShareRevoked(data) {
    const wsId   = parseInt(data.workspace_id);
    const wsName = data.workspace_name || 'Workspace';

    // 1. Notification
    const notification = {
        id: Date.now(),
        type: 'revoke',
        icon: 'no_accounts',
        title: 'Quyền workspace bị thu hồi',
        message: `<strong>${data.revoked_by.name}</strong> đã thu hồi quyền truy cập workspace "${_truncate(wsName, 30)}" của bạn`,
        time: new Date(),
        unread: true,
    };
    _addNotification(notification);
    _showRealtimeToast(notification);

    // 2. Remove sidebar item
    const sidebarItem = document.querySelector(`.fn-ws-item[data-ws-id="${wsId}"]`);
    if (sidebarItem) sidebarItem.remove();

    // 3. If currently inside that workspace, redirect back to personal workspace
    if (parseInt(window.__activeWorkspaceId) === wsId) {
        if (typeof showToast === 'function') {
            showToast(`Quyền truy cập workspace "${_truncate(wsName, 30)}" đã bị thu hồi. Đang chuyển về workspace cá nhân...`, 'warning');
        }
        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 2000);
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
