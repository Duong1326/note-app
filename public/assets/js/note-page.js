/**
 * note-page.js – Full-page note editor bootstrap adapter.
 *
 * Works for BOTH modes:
 *   CREATE – window.__FNP_EDIT_NOTE_ID is null  → POST new note
 *   EDIT   – window.__FNP_EDIT_NOTE_ID is a number → PUT existing note
 *
 * Runs AFTER all other scripts are loaded. Seeds state variables that
 * note-modal.js / auto-save.js expect, then wires keyboard shortcuts and
 * post-save navigation.
 *
 * Key design: zero business logic is duplicated.  We call the same
 * functions (setEditorContent, autoSaveReset, submitNoteForm …) that the
 * modal open/close handlers call.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {

        // ── 1. Read Blade-injected globals ─────────────────────────────────────
        const noteId      = window.__FNP_EDIT_NOTE_ID ?? null;  // null = create mode
        const title       = window.__FNP_NOTE_TITLE   ?? '';
        const content     = window.__FNP_NOTE_CONTENT ?? '';
        const labelIds    = window.__FNP_LABEL_IDS    ?? [];
        const attachments = window.__FNP_ATTACHMENTS  ?? [];
        const isOwner     = window.__FNP_IS_OWNER     ?? false;
        const isLocked    = window.__FNP_LOCKED       ?? false;

        const isCreateMode = noteId === null;

        // ── 2. Seed note-modal.js / auto-save.js state globals ─────────────────
        // _editingNoteId is a `let` at module scope in note-modal.js.
        // Setting it via window allows the auto-save dispatcher to read it.
        window._editingNoteId = noteId;   // null for create, number for edit
        window._editLockToken = null;

        // Seed _existingAttachments (from note-attachments.js)
        if (typeof _existingAttachments !== 'undefined') {
            // eslint-disable-next-line no-global-assign
            _existingAttachments = attachments;
        }

        // ── 3. Set editor content ───────────────────────────────────────────────
        if (typeof setEditorContent === 'function') {
            setEditorContent(content);
        }

        // ── 4. Seed label checkboxes ────────────────────────────────────────────
        document.querySelectorAll('input[name="label_ids[]"]').forEach(cb => {
            cb.checked = labelIds.includes(parseInt(cb.value));
        });

        // ── 5. Render existing attachments grid ─────────────────────────────────
        if (typeof renderExistingAttachments === 'function') {
            renderExistingAttachments();
        }

        // ── 6. Boot auto-save with appropriate baseline ─────────────────────────
        if (isCreateMode) {
            // New note: baseline is empty
            if (typeof autoSaveResetNew === 'function') autoSaveResetNew();
        } else {
            // Existing note: baseline is current server values
            if (typeof autoSaveReset === 'function') {
                autoSaveReset(title, content, labelIds);
            }
        }

        // ── 7. Update breadcrumb title live as user types ───────────────────────
        const titleInput = document.getElementById('modalNoteTitle');
        const breadcrumb = document.getElementById('fnpBreadcrumbTitle');
        if (titleInput && breadcrumb) {
            titleInput.addEventListener('input', () => {
                breadcrumb.textContent = titleInput.value.trim() || (isCreateMode ? 'Ghi chú mới' : 'Ghi chú không có tiêu đề');
            });
        }

        // ── 8. Slash command: watch content editor ──────────────────────────────
        const contentEditor = document.getElementById('modalNoteContent');
        if (contentEditor) {
            contentEditor.addEventListener('input', () => {
                if (typeof _slashMenuVisible !== 'undefined' && _slashMenuVisible) {
                    if (typeof handleSlashMenuInput === 'function') handleSlashMenuInput();
                } else {
                    const sel = window.getSelection();
                    if (!sel.rangeCount) return;
                    const node = sel.focusNode;
                    if (node && node.nodeType === Node.TEXT_NODE) {
                        const text = node.textContent;
                        const pos  = sel.focusOffset;
                        if (pos > 0 && text[pos - 1] === '/') {
                            if (typeof showSlashMenu === 'function') showSlashMenu();
                        }
                    }
                }
            });

            document.addEventListener('mousedown', e => {
                if (typeof _slashMenuVisible !== 'undefined' && _slashMenuVisible
                    && !e.target.closest('.fn-slash-menu')
                    && !e.target.closest('#modalNoteContent')) {
                    if (typeof hideSlashMenu === 'function') hideSlashMenu();
                }
            });
        }

        // ── 9. Keyboard shortcuts ───────────────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (typeof _slashMenuVisible !== 'undefined' && _slashMenuVisible) {
                if (typeof handleSlashMenuKeydown === 'function' && handleSlashMenuKeydown(e)) return;
            }
            if (e.key === 'Escape') {
                if (typeof _slashMenuVisible !== 'undefined' && _slashMenuVisible) {
                    if (typeof hideSlashMenu === 'function') hideSlashMenu();
                    return;
                }
            }
            // Ctrl/Cmd + S → save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (typeof submitNoteForm === 'function') submitNoteForm();
            }
        });

        // ── 10. Post-save navigation (override closeNewNoteModal) ───────────────
        //
        // CREATE mode: after submitNoteForm → prependNoteCard(note) is called first
        //   → we capture the new note's ID → then closeNewNoteModal fires
        //   → we redirect to /notes/{newId}/edit
        //
        // EDIT mode:   closeNewNoteModal → redirect to /dashboard
        //
        let _createdNoteId = null;

        // ── FIX 1: Guard card functions — they operate on dashboard DOM,
        //    not on the full-page editor. Override them so they don't
        //    silently fail or interfere before navigation. ──────────────────────
        window.patchNoteCard = function () { /* no-op on edit page */ };
        window.moveCardToTopOfUnpinned = function () { /* no-op on edit page */ };

        if (isCreateMode) {
            // Intercept prependNoteCard to grab the newly created note's ID
            window.prependNoteCard = function (note) {
                _createdNoteId = note?.id ?? null;
                // Don't actually prepend a card since we're navigating away
                // (the card will be rendered by the dashboard on next load)
            };
        } else {
            // In edit mode, prependNoteCard should never be called, but guard anyway
            window.prependNoteCard = function () { /* no-op on edit page */ };
        }

        window.closeNewNoteModal = function () {
            // Identical teardown as original closeNewNoteModal
            if (typeof autoSaveCancel      === 'function') autoSaveCancel();
            if (typeof closeImgPicker      === 'function') closeImgPicker();
            if (typeof closeSlashImgPicker === 'function') closeSlashImgPicker();

            if (isCreateMode && _createdNoteId) {
                // After creating → go to the new note's edit page
                window.location.replace(`/notes/${_createdNoteId}/edit`);
            } else {
                // After editing → back to dashboard, use replace() to bust bfcache
                window.location.replace('/dashboard');
            }
        };


        // ── 11. Locked notes: require unlock before editing ─────────────────────
        if (isOwner && isLocked && !isCreateMode) {
            const editor = contentEditor;
            if (editor)     editor.contentEditable = 'false';
            if (titleInput) titleInput.readOnly = true;

            if (typeof requireUnlock === 'function') {
                requireUnlock(noteId, (token) => {
                    window._editLockToken = token;
                    if (editor)     editor.contentEditable = 'true';
                    if (titleInput) { titleInput.readOnly = false; titleInput.focus(); }
                });
            }
        }

        // ── 12. Realtime sync on the edit page ─────────────────────────────────
        // Theo dõi thời điểm user gõ phím gần nhất để tránh ghi đè khi đang nhập
        let _userLastTypedAt = 0;
        const USER_TYPING_GRACE = 3000; // ms sau lần gõ cuối mới nhận update từ xa

        const titleInput2 = document.getElementById('modalNoteTitle');
        const contentEditor2 = document.getElementById('modalNoteContent');

        if (titleInput2) {
            titleInput2.addEventListener('input', () => { _userLastTypedAt = Date.now(); });
        }
        if (contentEditor2) {
            contentEditor2.addEventListener('input', () => { _userLastTypedAt = Date.now(); });
        }

        // Expose cho _applyRemoteUpdate
        window.__fnpUserLastTypedAt = () => _userLastTypedAt;
        window.__fnpTypingGrace = USER_TYPING_GRACE;

        // Only for existing notes (not create mode)
        if (!isCreateMode && noteId) {
            _initEditPageRealtime(noteId, isOwner);
        }

    }); // DOMContentLoaded

    // ── Realtime: join presence channel + listen for remote updates ─────────
    function _initEditPageRealtime(noteId, isOwner) {
        // Wait for Echo to be available (it initialises asynchronously)
        const _tryJoin = (retries = 0) => {
            if (!window.EchoInstance) {
                if (retries < 20) setTimeout(() => _tryJoin(retries + 1), 300);
                return;
            }
            _joinPresence(noteId);
            _listenUpdates(noteId);
        };
        _tryJoin();
    }

    function _joinPresence(noteId) {
        const presenceEl = document.getElementById('fnpPresenceAvatars');

        window.EchoInstance.join('note.' + noteId)
            .here((members) => {
                if (presenceEl) _renderPresence(presenceEl, members.filter(m => m.id !== window.__userId));
            })
            .joining((member) => {
                if (member.id === window.__userId || !presenceEl) return;
                if (!presenceEl.querySelector(`[data-uid="${member.id}"]`)) {
                    presenceEl.insertAdjacentHTML('beforeend', _avatarHtml(member));
                }
            })
            .leaving((member) => {
                presenceEl?.querySelector(`[data-uid="${member.id}"]`)?.remove();
            })
            .error((err) => console.warn('[FNP] Presence error:', err));
    }

    function _listenUpdates(noteId) {
        // ── Cách 1: hook vào echo-init.js (kênh private-user.X đã subscribe sẵn) ──
        // Không thể gọi .private().listen() lại trên cùng channel vì Echo sẽ tạo
        // instance mới mà không thực sự nhận event. Dùng hook array thay thế.
        if (!Array.isArray(window.__onNoteUpdatedHooks)) {
            window.__onNoteUpdatedHooks = [];
        }
        window.__onNoteUpdatedHooks.push(function (data) {
            if (String(data.note_id) !== String(noteId)) return;
            if (data.updated_by?.id === window.__userId) return;
            _applyRemoteUpdate(data);
        });

        // ── Cách 2: lắng nghe trực tiếp trên presence channel note.{noteId} ──
        // Server broadcast NoteUpdated lên cả channel này, nên đây là nguồn dự phòng
        // (presence channel đã được join bởi _joinPresence ở trên)
        window.EchoInstance
            .join('note.' + noteId)
            .listen('.note.updated', (data) => {
                if (data.updated_by?.id === window.__userId) return;
                _applyRemoteUpdate(data);
            });
    }

    // Áp dụng nội dung từ xa vào editor
    let _lastAppliedAt = 0;
    function _applyRemoteUpdate(data) {
        const now = Date.now();
        // Debounce: chống gọi 2 lần trong cùng 1 tick (hook + presence channel)
        if (now - _lastAppliedAt < 200) return;
        _lastAppliedAt = now;

        // Nếu user vừa gõ trong vòng typing-grace ms → hoãn lại 3s
        const lastTyped = typeof window.__fnpUserLastTypedAt === 'function'
            ? window.__fnpUserLastTypedAt() : 0;
        const grace = window.__fnpTypingGrace || 3000;
        if (now - lastTyped < grace) {
            console.log('[FNP] User is typing – delaying remote update by', grace - (now - lastTyped), 'ms');
            setTimeout(() => _applyRemoteUpdate(data), grace - (now - lastTyped) + 100);
            return;
        }

        console.log('[FNP] Applying remote update from', data.updated_by?.name, '| noteId:', data.note_id);

        const titleInput   = document.getElementById('modalNoteTitle');
        const contentEditor = document.getElementById('modalNoteContent');

        // Cập nhật tiêu đề
        if (titleInput) {
            titleInput.value = data.note_title || '';
            const bc = document.getElementById('fnpBreadcrumbTitle');
            if (bc) bc.textContent = data.note_title || 'Ghi chú không có tiêu đề';
        }

        // Cập nhật nội dung editor
        if (contentEditor) {
            if (typeof setEditorContent === 'function') {
                setEditorContent(data.note_content || '');
            } else {
                contentEditor.innerHTML = data.note_content || '';
            }
        }

        // Reset auto-save baseline để không ghi lại thay đổi từ xa
        if (typeof autoSaveReset === 'function') {
            const curLabels = window.__FNP_LABEL_IDS ?? [];
            autoSaveReset(data.note_title, data.note_content, curLabels);
        }

        _showRemoteUpdateBadge(data.updated_by?.name);
    }



    function _renderPresence(el, members) {
        el.innerHTML = members.map(_avatarHtml).join('');
    }

    function _avatarHtml(member) {
        const initials = (member.name || '?').substring(0, 2).toUpperCase();
        const img = member.avatar_url
            ? `<img src="${_esc(member.avatar_url)}" alt="${_esc(member.name)}">`
            : initials;
        return `<div class="fnp-presence-avatar" data-uid="${member.id}" title="${_esc(member.name)} đang xem">${img}</div>`;
    }

    function _showRemoteUpdateBadge(name) {
        const badge = document.getElementById('fnpRemoteUpdateBadge');
        if (!badge) return;
        badge.textContent = `✏ ${name || 'Ai đó'} vừa cập nhật`;
        badge.style.opacity = '1';
        clearTimeout(badge._hideTimer);
        badge._hideTimer = setTimeout(() => { badge.style.opacity = '0'; }, 3500);
    }

    function _esc(str) {
        const d = document.createElement('div');
        d.setAttribute('x', str || '');
        return d.outerHTML.slice(4, -2);
    }

})();
