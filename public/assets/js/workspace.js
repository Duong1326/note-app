/**
 * Fluid Notes – Workspace Management JS
 * Handles workspace switching, CRUD, lock, and share operations.
 */

(function () {
    'use strict';

    var _currentWsId = null; // workspace currently being managed in settings modal
    var _wsLockAction = null; // 'enable' | 'change' | 'disable'
    var _wsVerifyCallback = null; // callback after successful verify

    // ══════════════════════════════════════════════
    // Workspace Dropdown Toggle
    // ══════════════════════════════════════════════

    window.toggleWorkspaceDropdown = function () {
        var switcher = document.getElementById('workspaceSwitcher');
        var dropdown = document.getElementById('wsDropdown');
        if (!switcher || !dropdown) return;

        var isOpen = !dropdown.classList.contains('d-none');
        if (isOpen) {
            dropdown.classList.add('d-none');
            switcher.classList.remove('open');
        } else {
            dropdown.classList.remove('d-none');
            switcher.classList.add('open');
        }
    };

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
        var switcher = document.getElementById('workspaceSwitcher');
        var header = document.querySelector('.fn-ws-header');
        var isInsideSwitcher = switcher && switcher.contains(e.target);
        var isInsideHeader = header && header.contains(e.target);
        if (!isInsideSwitcher && !isInsideHeader) {
            var dropdown = document.getElementById('wsDropdown');
            if (dropdown) dropdown.classList.add('d-none');
            if (switcher) switcher.classList.remove('open');
        }
    });

    // ══════════════════════════════════════════════
    // Switch Workspace
    // ══════════════════════════════════════════════

    window.switchWorkspace = function (wsId, wsName) {
        // Check if workspace is locked — if so, verify first
        var item = document.querySelector('.fn-ws-item[data-ws-id="' + wsId + '"]');
        if (item && item.dataset.wsLocked === '1') {
            // Check if we have an unlock token in sessionStorage
            var token = null;
            try { token = sessionStorage.getItem('fn_ws_token_' + wsId); } catch (e) {}

            if (!token) {
                openWsVerifyModal(wsId, wsName, function () {
                    _doSwitchWorkspace(wsId, wsName);
                });
                return;
            }
        }

        _doSwitchWorkspace(wsId, wsName);
    };

    function _doSwitchWorkspace(wsId, wsName) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) return;

        // Remember which workspace we're leaving so we can clear its token
        var prevItem = document.querySelector('.fn-ws-item.active[data-ws-id]');
        var prevWsId = prevItem ? prevItem.dataset.wsId : null;

        fetch('/workspaces/' + wsId + '/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Clear token of the workspace we just LEFT (force re-auth on return)
                if (prevWsId && String(prevWsId) !== String(wsId)) {
                    try { sessionStorage.removeItem('fn_ws_token_' + prevWsId); } catch (e) {}
                }

                // Update UI
                var nameEl = document.getElementById('wsActiveName');
                if (nameEl) nameEl.textContent = wsName;

                // Highlight active item
                document.querySelectorAll('.fn-ws-item').forEach(function (el) {
                    el.classList.toggle('active', el.dataset.wsId == wsId);
                });

                // Close dropdown
                var dropdown = document.getElementById('wsDropdown');
                if (dropdown) dropdown.classList.add('d-none');
                var switcher = document.getElementById('workspaceSwitcher');
                if (switcher) switcher.classList.remove('open');

                // Reload dashboard to show notes from new workspace
                window.location.href = '/dashboard';
            }
        })
        .catch(function (err) {
            console.error('Switch workspace error:', err);
            if (typeof showToast === 'function') {
                showToast('Không thể chuyển workspace. Vui lòng thử lại.', 'error');
            }
        });
    }

    // ══════════════════════════════════════════════
    // Switch to Shared-With-Me View
    // ══════════════════════════════════════════════

    window.switchToSharedView = function () {
        var dropdown = document.getElementById('wsDropdown');
        if (dropdown) dropdown.classList.add('d-none');
        var switcher = document.getElementById('workspaceSwitcher');
        if (switcher) switcher.classList.remove('open');

        window.location.href = '/dashboard?view=shared';
    };

    // ══════════════════════════════════════════════
    // Create Workspace
    // ══════════════════════════════════════════════

    window.openCreateWorkspaceModal = function () {
        document.getElementById('wsCreateName').value = '';
        document.getElementById('wsCreateDesc').value = '';
        _showModal('createWorkspaceModal');
        setTimeout(function () { document.getElementById('wsCreateName').focus(); }, 200);

        // Close workspace dropdown
        var dropdown = document.getElementById('wsDropdown');
        if (dropdown) dropdown.classList.add('d-none');
        var switcher = document.getElementById('workspaceSwitcher');
        if (switcher) switcher.classList.remove('open');
    };

    window.closeCreateWorkspaceModal = function () {
        _hideModal('createWorkspaceModal');
    };

    window.submitCreateWorkspace = function () {
        var name = document.getElementById('wsCreateName').value.trim();
        var desc = document.getElementById('wsCreateDesc').value.trim();

        if (!name) {
            document.getElementById('wsCreateName').focus();
            return;
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]');

        fetch('/workspaces', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name: name, description: desc || null }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                closeCreateWorkspaceModal();
                if (typeof showToast === 'function') showToast(data.message, 'success');
                // Reload to refresh sidebar
                window.location.reload();
            } else {
                if (typeof showToast === 'function') showToast(data.message || 'Lỗi khi tạo workspace.', 'error');
            }
        })
        .catch(function () {
            if (typeof showToast === 'function') showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
        });
    };

    // ══════════════════════════════════════════════
    // Workspace Settings
    // ══════════════════════════════════════════════

    window.openWorkspaceSettings = function (wsId) {
        _currentWsId = wsId;
        var item = document.querySelector('.fn-ws-item[data-ws-id="' + wsId + '"]');
        if (!item) return;

        var wsName = item.dataset.wsName || '';
        var isLocked = item.dataset.wsLocked === '1';

        // Populate rename fields
        document.getElementById('wsSettingsName').value = wsName;
        document.getElementById('wsSettingsDesc').value = '';

        // Build lock section
        _buildLockSection(wsId, isLocked);

        // Load shares
        _loadWorkspaceShares(wsId);

        // Close workspace dropdown
        var dropdown = document.getElementById('wsDropdown');
        if (dropdown) dropdown.classList.add('d-none');
        var switcher = document.getElementById('workspaceSwitcher');
        if (switcher) switcher.classList.remove('open');

        _showModal('wsSettingsModal');
    };

    window.closeWorkspaceSettings = function () {
        _hideModal('wsSettingsModal');
        _currentWsId = null;
    };

    // ── Rename ──
    window.submitRenameWorkspace = function () {
        if (!_currentWsId) return;

        var name = document.getElementById('wsSettingsName').value.trim();
        var desc = document.getElementById('wsSettingsDesc').value.trim();
        if (!name) return;

        var csrfToken = document.querySelector('meta[name="csrf-token"]');

        fetch('/workspaces/' + _currentWsId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ name: name, description: desc || null }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                // Update sidebar item
                var item = document.querySelector('.fn-ws-item[data-ws-id="' + _currentWsId + '"]');
                if (item) {
                    item.dataset.wsName = name;
                    var nameEl = item.querySelector('.fn-ws-item-name');
                    if (nameEl) nameEl.textContent = name;
                }
                // Update active name if this is the active workspace
                var activeName = document.getElementById('wsActiveName');
                if (activeName && item && item.classList.contains('active')) {
                    activeName.textContent = name;
                }
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
            }
        });
    };

    // ── Delete ──
    window.confirmDeleteWorkspace = function () {
        if (!_currentWsId) return;

        if (!confirm('Bạn có chắc muốn xóa workspace này?\nTất cả ghi chú bên trong sẽ bị xóa vĩnh viễn!')) return;

        var csrfToken = document.querySelector('meta[name="csrf-token"]');

        fetch('/workspaces/' + _currentWsId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken.content,
                'Accept': 'application/json',
            },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                closeWorkspaceSettings();
                if (typeof showToast === 'function') showToast(data.message, 'success');

                // Clear the lock-verify token for the deleted workspace
                try { sessionStorage.removeItem('fn_ws_token_' + _currentWsId); } catch (e) {}

                // Update active workspace ID in JS so the next page load is correct
                if (data.redirect_workspace_id) {
                    window.__activeWorkspaceId = data.redirect_workspace_id;
                }

                // Redirect — session is already reset to default on the server
                window.location.href = '/dashboard';
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
            }
        });
    };

    // ══════════════════════════════════════════════
    // Workspace Lock
    // ══════════════════════════════════════════════

    function _buildLockSection(wsId, isLocked) {
        var container = document.getElementById('wsLockSection');
        if (!container) return;

        if (isLocked) {
            container.innerHTML =
                '<div class="fn-ws-lock-status locked">' +
                    '<span class="material-symbols-outlined">lock</span>' +
                    '<span>Workspace đã được khoá</span>' +
                '</div>' +
                '<div class="fn-ws-lock-actions">' +
                    '<button class="fn-ws-action-btn fn-ws-action-primary" onclick="openWsChangeLock()">' +
                        '<span class="material-symbols-outlined">key</span> Đổi mật khẩu' +
                    '</button>' +
                    '<button class="fn-ws-action-btn fn-ws-action-danger" onclick="openWsDisableLock()">' +
                        '<span class="material-symbols-outlined">no_encryption</span> Gỡ khoá' +
                    '</button>' +
                '</div>';
        } else {
            container.innerHTML =
                '<div class="fn-ws-lock-status unlocked">' +
                    '<span class="material-symbols-outlined">lock_open</span>' +
                    '<span>Workspace chưa được khoá</span>' +
                '</div>' +
                '<button class="fn-ws-action-btn fn-ws-action-primary" onclick="openWsEnableLock()">' +
                    '<span class="material-symbols-outlined">lock</span> Khoá bằng mật khẩu' +
                '</button>';
        }
    }

    window.openWsEnableLock = function () {
        _wsLockAction = 'enable';
        document.getElementById('wsLockModalTitle').textContent = 'Khoá workspace';
        document.getElementById('wsLockModalBody').innerHTML =
            '<div class="fn-form-group">' +
                '<label class="fn-form-label">Mật khẩu mới</label>' +
                '<input type="password" class="fn-form-input" id="wsLockPwd1" placeholder="Tối thiểu 4 ký tự..." minlength="4">' +
            '</div>' +
            '<div class="fn-form-group">' +
                '<label class="fn-form-label">Xác nhận mật khẩu</label>' +
                '<input type="password" class="fn-form-input" id="wsLockPwd2" placeholder="Nhập lại mật khẩu...">' +
            '</div>';
        _showModal('wsLockModal');
        setTimeout(function () { document.getElementById('wsLockPwd1').focus(); }, 200);
    };

    window.openWsChangeLock = function () {
        _wsLockAction = 'change';
        document.getElementById('wsLockModalTitle').textContent = 'Đổi mật khẩu';
        document.getElementById('wsLockModalBody').innerHTML =
            '<div class="fn-form-group">' +
                '<label class="fn-form-label">Mật khẩu hiện tại</label>' +
                '<input type="password" class="fn-form-input" id="wsLockCurrentPwd" placeholder="Mật khẩu hiện tại...">' +
            '</div>' +
            '<div class="fn-form-group">' +
                '<label class="fn-form-label">Mật khẩu mới</label>' +
                '<input type="password" class="fn-form-input" id="wsLockPwd1" placeholder="Tối thiểu 4 ký tự..." minlength="4">' +
            '</div>' +
            '<div class="fn-form-group">' +
                '<label class="fn-form-label">Xác nhận mật khẩu mới</label>' +
                '<input type="password" class="fn-form-input" id="wsLockPwd2" placeholder="Nhập lại mật khẩu...">' +
            '</div>';
        _showModal('wsLockModal');
        setTimeout(function () { document.getElementById('wsLockCurrentPwd').focus(); }, 200);
    };

    window.openWsDisableLock = function () {
        _wsLockAction = 'disable';
        document.getElementById('wsLockModalTitle').textContent = 'Gỡ khoá workspace';
        document.getElementById('wsLockModalBody').innerHTML =
            '<p class="fn-ws-danger-text">Nhập mật khẩu hiện tại để gỡ khoá workspace.</p>' +
            '<div class="fn-form-group">' +
                '<input type="password" class="fn-form-input" id="wsLockCurrentPwd" placeholder="Mật khẩu hiện tại...">' +
            '</div>';
        _showModal('wsLockModal');
        setTimeout(function () { document.getElementById('wsLockCurrentPwd').focus(); }, 200);
    };

    window.closeWsLockModal = function () {
        _hideModal('wsLockModal');
    };

    window.submitWsLockAction = function () {
        if (!_currentWsId) return;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        var url, method, body;

        if (_wsLockAction === 'enable') {
            var pwd1 = document.getElementById('wsLockPwd1').value;
            var pwd2 = document.getElementById('wsLockPwd2').value;
            if (!pwd1 || pwd1.length < 4) {
                if (typeof showToast === 'function') showToast('Mật khẩu tối thiểu 4 ký tự.', 'error');
                return;
            }
            if (pwd1 !== pwd2) {
                if (typeof showToast === 'function') showToast('Mật khẩu xác nhận không khớp.', 'error');
                return;
            }
            url = '/workspaces/' + _currentWsId + '/lock/enable';
            method = 'POST';
            body = { password: pwd1, password_confirmation: pwd2 };
        } else if (_wsLockAction === 'change') {
            var cur = document.getElementById('wsLockCurrentPwd').value;
            var pwd1 = document.getElementById('wsLockPwd1').value;
            var pwd2 = document.getElementById('wsLockPwd2').value;
            if (!cur || !pwd1 || pwd1.length < 4) {
                if (typeof showToast === 'function') showToast('Vui lòng điền đầy đủ thông tin.', 'error');
                return;
            }
            if (pwd1 !== pwd2) {
                if (typeof showToast === 'function') showToast('Mật khẩu xác nhận không khớp.', 'error');
                return;
            }
            url = '/workspaces/' + _currentWsId + '/lock/password';
            method = 'PUT';
            body = { current_password: cur, password: pwd1, password_confirmation: pwd2 };
        } else if (_wsLockAction === 'disable') {
            var cur = document.getElementById('wsLockCurrentPwd').value;
            if (!cur) {
                if (typeof showToast === 'function') showToast('Vui lòng nhập mật khẩu.', 'error');
                return;
            }
            url = '/workspaces/' + _currentWsId + '/lock';
            method = 'DELETE';
            body = { password: cur };
        }

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                closeWsLockModal();
                if (typeof showToast === 'function') showToast(data.message, 'success');

                // Update lock status in sidebar item
                var item = document.querySelector('.fn-ws-item[data-ws-id="' + _currentWsId + '"]');
                if (item) {
                    var isNowLocked = _wsLockAction === 'enable' || _wsLockAction === 'change';
                    if (_wsLockAction === 'disable') isNowLocked = false;
                    item.dataset.wsLocked = isNowLocked ? '1' : '0';
                    var icon = item.querySelector('.fn-ws-item-icon');
                    if (icon) icon.textContent = isNowLocked ? 'lock' : 'folder';
                }

                // Store token if returned
                if (data.token) {
                    try { sessionStorage.setItem('fn_ws_token_' + _currentWsId, data.token); } catch (e) {}
                }

                // Rebuild lock section in settings
                _buildLockSection(_currentWsId, _wsLockAction !== 'disable');
            } else {
                if (typeof showToast === 'function') showToast(data.message, 'error');
            }
        });
    };

    // ══════════════════════════════════════════════
    // Workspace Verify (for locked workspace access)
    // ══════════════════════════════════════════════

    window.openWsVerifyModal = function (wsId, wsName, callback) {
        _currentWsId = wsId;
        _wsVerifyCallback = callback;
        document.getElementById('wsVerifyName').textContent = wsName;
        document.getElementById('wsVerifyPassword').value = '';
        var errEl = document.getElementById('wsVerifyError');
        if (errEl) errEl.classList.add('d-none');
        _showModal('wsVerifyModal');
        setTimeout(function () { document.getElementById('wsVerifyPassword').focus(); }, 200);
    };

    window.closeWsVerifyModal = function () {
        _hideModal('wsVerifyModal');
        _wsVerifyCallback = null;
    };

    window.submitWsVerify = function () {
        if (!_currentWsId) return;
        var password = document.getElementById('wsVerifyPassword').value;
        if (!password) return;

        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        fetch('/workspaces/' + _currentWsId + '/lock/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ password: password }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Store token for this workspace
                if (data.token) {
                    try { sessionStorage.setItem('fn_ws_token_' + _currentWsId, data.token); } catch (e) {}                }
                // Clear token of the workspace we're leaving (force re-auth on return)
                var prevItem2 = document.querySelector('.fn-ws-item.active[data-ws-id]');
                var prevWsId2 = prevItem2 ? prevItem2.dataset.wsId : null;
                if (prevWsId2 && String(prevWsId2) !== String(_currentWsId)) {
                    try { sessionStorage.removeItem('fn_ws_token_' + prevWsId2); } catch (e) {}
                }
                closeWsVerifyModal();
                // Navigate immediately — session already updated by verify endpoint
                window.location.href = '/dashboard';
            } else {
                var errEl = document.getElementById('wsVerifyError');
                if (errEl) {
                    errEl.textContent = data.message || 'Mật khẩu không đúng.';
                    errEl.classList.remove('d-none');
                }
            }
        });
    };

    // ══════════════════════════════════════════════
    // Workspace Sharing
    // ══════════════════════════════════════════════

    function _loadWorkspaceShares(wsId) {
        var container = document.getElementById('wsSharesList');
        if (!container) return;
        container.innerHTML = '<div class="text-center text-muted" style="font-size:0.8125rem;padding:0.5rem;">Đang tải...</div>';

        fetch('/workspaces/' + wsId + '/shares', {
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.shares.length > 0) {
                container.innerHTML = data.shares.map(function (s) {
                    var initials = (s.name || '??').substring(0, 2).toUpperCase();
                    var avatarHtml = s.avatar_url
                        ? '<img src="' + s.avatar_url + '" alt="' + s.name + '">'
                        : initials;

                    return '<div class="fn-ws-share-item" data-share-id="' + s.id + '">' +
                        '<div class="fn-ws-share-user">' +
                            '<div class="fn-ws-share-avatar">' + avatarHtml + '</div>' +
                            '<span class="fn-ws-share-name">' + s.name + '</span>' +
                        '</div>' +
                        '<div class="fn-ws-share-actions">' +
                            '<select class="fn-ws-share-perm-select" onchange="updateWsSharePerm(' + wsId + ',' + s.id + ',this.value)">' +
                                '<option value="read"' + (s.permission === 'read' ? ' selected' : '') + '>Chỉ đọc</option>' +
                                '<option value="edit"' + (s.permission === 'edit' ? ' selected' : '') + '>Chỉnh sửa</option>' +
                            '</select>' +
                            '<button class="fn-ws-share-remove" onclick="removeWsShare(' + wsId + ',' + s.id + ')" title="Thu hồi">' +
                                '<span class="material-symbols-outlined">close</span>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                }).join('');
            } else {
                container.innerHTML = '<div class="text-center text-muted" style="font-size:0.8125rem;padding:0.5rem;opacity:0.6;">Chưa chia sẻ với ai</div>';
            }
        })
        .catch(function () {
            container.innerHTML = '';
        });
    }

    window.submitShareWorkspace = function () {
        if (!_currentWsId) return;
        var emailInput = document.getElementById('wsShareEmail');
        var permSelect = document.getElementById('wsSharePermission');
        if (!emailInput || !permSelect) return;

        var emailStr = emailInput.value.trim();
        if (!emailStr) return;

        // Support comma-separated emails
        var emails = emailStr.split(/[,;\s]+/).filter(function (e) { return e.length > 0; });
        var permission = permSelect.value;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        fetch('/workspaces/' + _currentWsId + '/shares', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ emails: emails, permission: permission }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                emailInput.value = '';
                _loadWorkspaceShares(_currentWsId);
            } else {
                var msg = data.message || 'Lỗi khi chia sẻ.';
                if (data.errors) {
                    var allErrors = Object.values(data.errors).flat();
                    msg = allErrors.join('\n');
                }
                if (typeof showToast === 'function') showToast(msg, 'error');
            }
        });
    };

    window.updateWsSharePerm = function (wsId, shareId, permission) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        fetch('/workspaces/' + wsId + '/shares/' + shareId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ permission: permission }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
            }
        });
    };

    window.removeWsShare = function (wsId, shareId) {
        if (!confirm('Thu hồi quyền truy cập workspace của người dùng này?')) return;

        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        fetch('/workspaces/' + wsId + '/shares/' + shareId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                // Remove item from DOM
                var item = document.querySelector('.fn-ws-share-item[data-share-id="' + shareId + '"]');
                if (item) item.remove();

                // Check if list is empty
                var container = document.getElementById('wsSharesList');
                if (container && container.children.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted" style="font-size:0.8125rem;padding:0.5rem;opacity:0.6;">Chưa chia sẻ với ai</div>';
                }
            }
        });
    };

    // ══════════════════════════════════════════════
    // Modal Helpers
    // ══════════════════════════════════════════════

    function _showModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function _hideModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('show');
            // Only restore scroll if no other modals are open
            var anyOpen = document.querySelector('.fn-modal-overlay.show');
            if (!anyOpen) document.body.style.overflow = '';
        }
    }

})();
