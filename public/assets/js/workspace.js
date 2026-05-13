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
            try { token = sessionStorage.getItem('fn_ws_token_' + wsId); } catch (e) { }

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
                        try { sessionStorage.removeItem('fn_ws_token_' + prevWsId); } catch (e) { }
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
                    // Redirect to dashboard — session is already switched to the new workspace
                    window.location.href = '/dashboard';
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
                    try { sessionStorage.removeItem('fn_ws_token_' + _currentWsId); } catch (e) { }

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
                        try { sessionStorage.setItem('fn_ws_token_' + _currentWsId, data.token); } catch (e) { }
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
        var pwInput = document.getElementById('wsVerifyPassword');
        if (pwInput) { pwInput.value = ''; pwInput.type = 'password'; }
        var icon = document.getElementById('wsVerifyPwIcon');
        if (icon) icon.textContent = 'visibility';
        var errEl = document.getElementById('wsVerifyError');
        if (errEl) errEl.classList.add('d-none');
        _showModal('wsVerifyModal');
        setTimeout(function () { if (pwInput) pwInput.focus(); }, 200);
    };

    window.toggleWsVerifyPw = function () {
        var input = document.getElementById('wsVerifyPassword');
        var icon = document.getElementById('wsVerifyPwIcon');
        if (!input) return;
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        if (icon) icon.textContent = isHidden ? 'visibility_off' : 'visibility';
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
                        try { sessionStorage.setItem('fn_ws_token_' + _currentWsId, data.token); } catch (e) { }
                    }
                    // Clear token of the workspace we're leaving (force re-auth on return)
                    var prevItem2 = document.querySelector('.fn-ws-item.active[data-ws-id]');
                    var prevWsId2 = prevItem2 ? prevItem2.dataset.wsId : null;
                    if (prevWsId2 && String(prevWsId2) !== String(_currentWsId)) {
                        try { sessionStorage.removeItem('fn_ws_token_' + prevWsId2); } catch (e) { }
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

    var _wsShareTrigger = null;
    var _wsEmailChips   = [];   // { email, valid }
    var _wsExistingShares = [];

    // ── Open / close ────────────────────────────────────────
    window.openWsShareModal = function (wsId, wsName, triggerBtn) {
        _currentWsId      = wsId;
        _wsShareTrigger   = triggerBtn || null;
        _wsEmailChips     = [];
        _wsExistingShares = [];

        // Reset UI
        _wssRenderChips();
        var emailInput = document.getElementById('wsShareModalEmail');
        if (emailInput) emailInput.value = '';
        _wssHideError();
        var recipSection = document.getElementById('wsShareRecipientsSection');
        if (recipSection) recipSection.classList.add('d-none');
        var recipList = document.getElementById('wsShareModalList');
        if (recipList) recipList.innerHTML = '';
        var permRead = document.getElementById('wsPermRead');
        if (permRead) permRead.checked = true;

        var subtitle = document.getElementById('wsShareModalSubtitle');
        if (subtitle) subtitle.textContent = wsName || '';

        // Position popover below trigger button (mirrors note-share.js popover mode)
        var popover = document.getElementById('wsSharePopover');
        if (!popover) return;
        var card = document.getElementById('wsSharePopoverCard');

        if (triggerBtn) {
            var rect = triggerBtn.getBoundingClientRect();
            if (card) {
                card.style.position = 'fixed';
                card.style.top    = (rect.bottom + 8) + 'px';
                card.style.right  = (window.innerWidth - rect.right) + 'px';
                card.style.left   = 'auto';
                card.style.margin = '0';
                card.style.transform = 'none';
            }
        } else {
            if (card) {
                card.style.position = '';
                card.style.top = card.style.right = card.style.left = card.style.margin = card.style.transform = '';
            }
        }

        popover.style.display = 'flex'; // overlay uses flex
        document.body.style.overflow = '';  // don't lock scroll (popover, not fullscreen)
        setTimeout(function () {
            popover.classList.add('show');
            if (emailInput) emailInput.focus();
        }, 10);

        // Fetch existing recipients
        _wssLoadRecipients(wsId);
    };

    window.closeWsShareModal = function () {
        var popover = document.getElementById('wsSharePopover');
        if (!popover) return;
        popover.classList.remove('show');
        setTimeout(function () { popover.style.display = 'none'; }, 200);
        _wsShareTrigger = null;
    };

    // Close on outside click
    document.addEventListener('click', function (e) {
        var popover = document.getElementById('wsSharePopover');
        if (!popover || popover.style.display === 'none') return;
        if (popover.contains(e.target)) return;
        if (_wsShareTrigger && _wsShareTrigger.contains(e.target)) return;
        closeWsShareModal();
    });

    // ── Email chip helpers (mirrors note-share.js) ────────────
    window.wssFocusEmailInput = function () {
        var el = document.getElementById('wsShareModalEmail');
        if (el) el.focus();
    };

    function _wssAddChip(email) {
        if (!email) return;
        var cleaned = email.replace(/,/g, '').trim();
        if (!cleaned) return;
        if (_wsEmailChips.some(function (c) { return c.email === cleaned; })) {
            document.getElementById('wsShareModalEmail').value = '';
            return;
        }
        var isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleaned);
        _wsEmailChips.push({ email: cleaned, valid: isValid });
        _wssRenderChips();
        document.getElementById('wsShareModalEmail').value = '';
        _wssHideError();
    }

    window.wssRemoveChip = function (index) {
        _wsEmailChips.splice(index, 1);
        _wssRenderChips();
    };

    function _wssRenderChips() {
        var container = document.getElementById('wsEmailChipContainer');
        var input = document.getElementById('wsShareModalEmail');
        if (!container || !input) return;
        container.querySelectorAll('.fn-email-chip').forEach(function (el) { el.remove(); });
        _wsEmailChips.forEach(function (chip, i) {
            var el = document.createElement('span');
            el.className = 'fn-email-chip' + (chip.valid ? '' : ' invalid');
            el.innerHTML = _wssEscapeHtml(chip.email) +
                '<button type="button" class="fn-email-chip-remove" onclick="wssRemoveChip(' + i + ')" tabindex="-1">' +
                '<span class="material-symbols-outlined">close</span></button>';
            container.insertBefore(el, input);
        });
    }

    // ── Submit share ────────────────────────────────────────
    window.submitWsShareFromModal = function (e) {
        if (e) e.preventDefault();
        if (!_currentWsId) return;

        // Finalize typed text
        var rawInput = document.getElementById('wsShareModalEmail').value.trim();
        if (rawInput) _wssAddChip(rawInput);

        if (_wsEmailChips.length === 0) { _wssShowError('Vui lòng nhập ít nhất một địa chỉ email.'); return; }
        var invalidChips = _wsEmailChips.filter(function (c) { return !c.valid; });
        if (invalidChips.length > 0) { _wssShowError('Email không hợp lệ: ' + invalidChips.map(function (c) { return c.email; }).join(', ')); return; }

        var permission = document.querySelector('input[name="wsSharePermission"]:checked');
        var perm = permission ? permission.value : 'read';
        var emails = _wsEmailChips.map(function (c) { return c.email; });

        var btn = document.getElementById('wsShareSubmitBtn');
        if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }
        _wssHideError();

        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        fetch('/workspaces/' + _currentWsId + '/shares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ emails: emails, permission: perm }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                _wsEmailChips = [];
                _wssRenderChips();
                _wssLoadRecipients(_currentWsId);
            } else {
                var msg = data.message || 'Lỗi khi chia sẻ.';
                if (data.errors) msg = Object.values(data.errors).flat().join('\n');
                _wssShowError(msg);
            }
        })
        .finally(function () {
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        });
    };

    // ── Load & render recipients (mirrors renderRecipients in note-share.js) ──
    function _wssLoadRecipients(wsId) {
        fetch('/workspaces/' + wsId + '/shares', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                _wsExistingShares = data.shares;
                _wssRenderRecipients();
                var section = document.getElementById('wsShareRecipientsSection');
                if (section) section.classList.toggle('d-none', data.shares.length === 0);
            }
        });
    }

    function _wssRenderRecipients() {
        var list = document.getElementById('wsShareModalList');
        if (!list) return;
        list.innerHTML = '';
        _wsExistingShares.forEach(function (s) {
            var initials = (s.name || '?').slice(0, 2).toUpperCase();
            var avatarHtml = s.avatar_url
                ? '<img src="' + _wssEscapeHtml(s.avatar_url) + '" alt="' + _wssEscapeHtml(s.name) + '">'
                : initials;
            var row = document.createElement('div');
            row.className = 'fn-share-recipient';
            row.dataset.shareId = s.id;
            row.innerHTML =
                '<div class="fn-share-avatar">' + avatarHtml + '</div>' +
                '<div class="fn-share-recipient-info">' +
                    '<div class="fn-share-recipient-name">' + _wssEscapeHtml(s.name) + '</div>' +
                    '<div class="fn-share-recipient-email">' + _wssEscapeHtml(s.email || '') + '</div>' +
                '</div>' +
                '<select class="fn-share-perm-select" onchange="wssUpdatePerm(' + s.id + ',this.value)">' +
                    '<option value="read"' + (s.permission === 'read' ? ' selected' : '') + '>Chỉ đọc</option>' +
                    '<option value="edit"' + (s.permission === 'edit' ? ' selected' : '') + '>Chỉnh sửa</option>' +
                '</select>' +
                '<button type="button" class="fn-share-revoke-btn" onclick="wssRevokeShare(' + _currentWsId + ',' + s.id + ')" title="Thu hồi quyền">' +
                    '<span class="material-symbols-outlined">person_remove</span>' +
                '</button>';
            list.appendChild(row);
        });
    }

    window.wssUpdatePerm = function (shareId, permission) {
        if (!_currentWsId) return;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        fetch('/workspaces/' + _currentWsId + '/shares/' + shareId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ permission: permission }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && typeof showToast === 'function') showToast(data.message, 'success');
        });
    };

    window.wssRevokeShare = function (wsId, shareId) {
        if (!confirm('Thu hồi quyền truy cập workspace của người dùng này?')) return;
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        fetch('/workspaces/' + wsId + '/shares/' + shareId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                _wsExistingShares = _wsExistingShares.filter(function (s) { return s.id !== shareId; });
                _wssRenderRecipients();
                var section = document.getElementById('wsShareRecipientsSection');
                if (section) section.classList.toggle('d-none', _wsExistingShares.length === 0);
            }
        });
    };

    // ── Chip input key handlers (wired via DOMContentLoaded) ─
    document.addEventListener('DOMContentLoaded', function () {
        var emailInput = document.getElementById('wsShareModalEmail');
        if (emailInput) {
            emailInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); _wssAddChip(this.value.trim()); }
                else if (e.key === 'Backspace' && this.value === '' && _wsEmailChips.length > 0) {
                    _wsEmailChips.pop(); _wssRenderChips();
                }
            });
            emailInput.addEventListener('blur', function () { if (this.value.trim()) _wssAddChip(this.value.trim()); });
        }
    });

    // ── Helpers ───────────────────────────────────────────
    function _wssShowError(msg) {
        var el = document.getElementById('wsShareEmailError');
        if (el) { el.textContent = msg; el.classList.remove('d-none'); }
    }
    function _wssHideError() {
        var el = document.getElementById('wsShareEmailError');
        if (el) el.classList.add('d-none');
    }
    function _wssEscapeHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})();
