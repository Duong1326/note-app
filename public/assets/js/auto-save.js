/**
 * auto-save.js – Automatic note saving while editing OR creating.
 *
 * Watches title, content, labels, and pending attachments.
 * After 1.5 s of inactivity it:
 *   - For EXISTING notes  → PUT /notes/{id}  (+ upload any pending files)
 *   - For NEW notes       → POST /notes first, gets an ID, then handles files
 *
 * Depends on (globals from other scripts):
 *   app.js             – apiFetch, showToast
 *   note-cards.js      – patchNoteCard, prependNoteCard, moveCardToTopOfUnpinned, updateCardThumbnail
 *   note-attachments.js– _pendingFiles, _existingAttachments, uploadPendingFilesParallel,
 *                        renderPendingPreviews, updateModalThumbnail
 *   note-modal.js      – _editingNoteId, _editLockToken, getEditorContent, uploadInlineContentImages
 */

(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────── */
    const AUTO_SAVE_DELAY = 1500;   // ms after user stops typing/selecting

    /* ── State ───────────────────────────────────────── */
    let _autoSaveTimer     = null;
    let _isSaving          = false;
    let _lastSavedTitle    = null;
    let _lastSavedContent  = null;
    let _lastSavedLabels   = null;
    let _lastPendingCount  = 0;     // track _pendingFiles.length to detect new uploads

    /* ── Status indicator element ────────────────────── */
    let $statusEl = null;

    /* ── Boot ────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        const titleInput   = document.getElementById('modalNoteTitle');
        const contentEl    = document.getElementById('modalNoteContent');
        const labelsChips  = document.getElementById('modalLabelsChips');

        if (titleInput)  titleInput.addEventListener('input',   _scheduleAutoSave);
        if (contentEl)   contentEl.addEventListener('input',    _scheduleAutoSave);
        if (labelsChips) labelsChips.addEventListener('change', _scheduleAutoSave);

        // Detect when files are added to the pending queue via MutationObserver
        const pendingPreviews = document.getElementById('pendingPreviews');
        if (pendingPreviews) {
            new MutationObserver(() => {
                const count = (typeof _pendingFiles !== 'undefined') ? _pendingFiles.length : 0;
                if (count > _lastPendingCount) {
                    _lastPendingCount = count;
                    _scheduleAutoSave();
                }
            }).observe(pendingPreviews, { childList: true });
        }

        // Detect when slash menu inserts an inline image block (fn-content-image-block)
        if (contentEl) {
            new MutationObserver((mutations) => {
                const hasNewImage = mutations.some(m =>
                    [...m.addedNodes].some(n =>
                        n.nodeType === Node.ELEMENT_NODE &&
                        (n.classList?.contains('fn-content-image-block') ||
                         n.querySelector?.('.fn-content-image-block'))
                    )
                );
                if (hasNewImage) _scheduleAutoSave();
            }).observe(contentEl, { childList: true, subtree: true });
        }

        _buildStatusEl();
    });

    /* ── Public API (called from note-modal.js) ──────── */

    /** Called when the edit modal opens for an EXISTING note. */
    window.autoSaveReset = function (title, content, labelIds) {
        clearTimeout(_autoSaveTimer);
        _isSaving          = false;
        _lastSavedTitle    = title;
        _lastSavedContent  = content;
        _lastSavedLabels   = (labelIds ?? []).map(String).sort().join(',');
        _lastPendingCount  = 0;
        _setStatus('idle');
    };

    /** Called when the NEW note modal opens — baseline is empty. */
    window.autoSaveResetNew = function () {
        clearTimeout(_autoSaveTimer);
        _isSaving          = false;
        _lastSavedTitle    = '';
        _lastSavedContent  = '';
        _lastSavedLabels   = '';
        _lastPendingCount  = 0;
        _setStatus('idle');
    };

    /** Called when the modal closes — cancel any pending save. */
    window.autoSaveCancel = function () {
        clearTimeout(_autoSaveTimer);
        _isSaving         = false;
        _lastPendingCount = 0;
        _setStatus('idle');
    };

    /* ── Schedule ────────────────────────────────────── */

    function _scheduleAutoSave() {
        // On the full-page editor, always allow auto-save
        const isPageContext = !!document.getElementById('noteEditPage');
        if (!isPageContext) {
            // Dashboard modal context: only run when the modal is visible
            const modal = document.getElementById('newNoteModal');
            if (!modal || !modal.classList.contains('show')) return;
        }

        clearTimeout(_autoSaveTimer);
        _setStatus('pending');
        _autoSaveTimer = setTimeout(_doAutoSave, AUTO_SAVE_DELAY);
    }

    /* ── Dispatcher ──────────────────────────────────── */

    async function _doAutoSave() {
        if (_isSaving) return;

        const title = document.getElementById('modalNoteTitle')?.value?.trim() ?? '';

        // Never auto-save without a title
        if (!title) {
            _setStatus('idle');
            return;
        }

        const isEditing =
            typeof _editingNoteId !== 'undefined' && _editingNoteId !== null;

        if (isEditing) {
            await _saveExistingNote(title);
        } else {
            await _createNewNote(title);
        }
    }

    /* ── Save existing note ──────────────────────────── */

    async function _saveExistingNote(title) {
        const content   = _getContent();
        const labelIds  = _getLabelIds();
        const labelsKey = labelIds.map(String).sort().join(',');
        const hasPending = _hasPendingFiles();

        // Nothing changed and no pending uploads → skip
        if (
            !hasPending          &&
            title    === _lastSavedTitle    &&
            content  === _lastSavedContent  &&
            labelsKey === _lastSavedLabels
        ) {
            _setStatus('idle');
            return;
        }

        _isSaving = true;
        _setStatus('saving');

        try {
            const noteId    = _editingNoteId;
            const lockToken = typeof _editLockToken !== 'undefined' ? _editLockToken : null;

            // 1. Upload any inline content images (e.g. pasted/dropped into editor)
            if (typeof uploadInlineContentImages === 'function') {
                await uploadInlineContentImages(noteId, lockToken);
            }

            const finalContent = _getContent();
            const body = new URLSearchParams({ title, content: finalContent });
            labelIds.forEach(id => body.append('label_ids[]', id));

            // 2. PUT note text/labels
            const headers = lockToken ? { 'X-Note-Token': lockToken } : {};
            const res  = await apiFetch(`/notes/${noteId}`, 'PUT', body, headers);
            const data = await res.json();
            if (!res.ok) { _setStatus('idle'); return; }

            // 3. Upload pending attachment files
            const uploaded = await _flushPendingFiles(noteId, lockToken);

            // 4. Patch dashboard card
            const allAtts = [
                ...(typeof _existingAttachments !== 'undefined' ? _existingAttachments : []),
                ...uploaded,
            ];
            const noteForCard = { ...data.note, attachments: allAtts };
            if (typeof patchNoteCard === 'function') patchNoteCard(noteId, noteForCard);

            const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
            if (col) {
                if (uploaded.length > 0 && typeof updateCardThumbnail === 'function') {
                    updateCardThumbnail(col, allAtts);
                }
                if (typeof moveCardToTopOfUnpinned === 'function') moveCardToTopOfUnpinned(col);
            }

            // 5. Update baselines
            _lastSavedTitle   = title;
            _lastSavedContent = finalContent;
            _lastSavedLabels  = labelsKey;
            _lastPendingCount = 0;

            _setStatus('saved');
            setTimeout(() => _setStatus('idle'), 2000);

        } catch {
            _setStatus('idle');
        } finally {
            _isSaving = false;
        }
    }

    /* ── Create new note ─────────────────────────────── */

    async function _createNewNote(title) {
        _isSaving = true;
        _setStatus('saving');

        try {
            const content  = _getContent();
            const labelIds = _getLabelIds();

            // 1. POST – create the note
            const body = new URLSearchParams({ title, content });
            labelIds.forEach(id => body.append('label_ids[]', id));

            const res  = await apiFetch(window.FN_STORE_URL, 'POST', body);
            const data = await res.json();
            if (!res.ok) { _setStatus('idle'); return; }

            const noteId = data.note.id;

            // 2. Switch modal to EDIT mode so future auto-saves use PUT
            _editingNoteId = noteId;   // global let from note-modal.js

            // 3. Upload inline content images (base64 src → Cloudinary URL)
            if (typeof uploadInlineContentImages === 'function') {
                await uploadInlineContentImages(noteId, null);
            }

            // If content changed after inline upload, persist it
            const finalContent = _getContent();
            if (finalContent !== content) {
                const updateBody = new URLSearchParams({ title, content: finalContent });
                labelIds.forEach(id => updateBody.append('label_ids[]', id));
                await apiFetch(`/notes/${noteId}`, 'PUT', updateBody).catch(() => {});
            }

            // 4. Upload pending attachment files
            const uploaded = await _flushPendingFiles(noteId, null);

            // 5. Prepend card on dashboard
            const noteForCard = {
                ...data.note,
                content: finalContent,
                attachments: uploaded,
            };
            if (typeof prependNoteCard === 'function') prependNoteCard(noteForCard);

            // 6. Update baselines for subsequent auto-saves
            _lastSavedTitle   = title;
            _lastSavedContent = finalContent;
            _lastSavedLabels  = labelIds.map(String).sort().join(',');
            _lastPendingCount = 0;

            _setStatus('saved');
            setTimeout(() => _setStatus('idle'), 2000);

        } catch {
            _setStatus('idle');
        } finally {
            _isSaving = false;
        }
    }

    /* ── Upload pending attachment files ─────────────── */

    /**
     * Upload all queued _pendingFiles, update _existingAttachments,
     * clear the queue, and refresh the attachment panel UI.
     * Returns the array of successfully uploaded attachment objects.
     */
    async function _flushPendingFiles(noteId, lockToken) {
        if (!_hasPendingFiles()) return [];

        try {
            const files   = [..._pendingFiles];
            if (typeof uploadPendingFilesParallel !== 'function') return [];

            const results = await uploadPendingFilesParallel(noteId, files, lockToken);
            // uploadPendingFilesParallel already clears _pendingFiles internally

            // Merge into existing attachments
            if (typeof _existingAttachments !== 'undefined') {
                _existingAttachments = [...(_existingAttachments || []), ...results];
            }

            // Refresh attachment panel UI
            if (typeof renderPendingPreviews   === 'function') renderPendingPreviews();
            if (typeof updateModalThumbnail    === 'function') updateModalThumbnail();

            return results;
        } catch {
            return [];
        }
    }

    /* ── Helpers ─────────────────────────────────────── */

    function _getContent() {
        return typeof getEditorContent === 'function' ? getEditorContent() : '';
    }

    function _getLabelIds() {
        return [...document.querySelectorAll('input[name="label_ids[]"]:checked')]
            .map(cb => cb.value);
    }

    function _hasPendingFiles() {
        return typeof _pendingFiles !== 'undefined' && _pendingFiles.length > 0;
    }

    /* ── Status indicator ────────────────────────────── */

    function _buildStatusEl() {
        // Use the pre-rendered span in the modal header
        $statusEl = document.getElementById('modalAutoSaveStatus');
    }

    const _statusConfig = {
        idle:    { icon: '',           cls: '' },
        pending: { icon: 'edit',       cls: 'pending' },
        saving:  { icon: 'cloud_sync', cls: 'saving'  },
        saved:   { icon: 'cloud_done', cls: 'saved'   },
    };

    function _setStatus(state) {
        if (!$statusEl) {
            _buildStatusEl();
            if (!$statusEl) return;
        }
        const cfg = _statusConfig[state] ?? _statusConfig.idle;
        if (!cfg.icon) {
            $statusEl.innerHTML = '';
            $statusEl.className = 'fn-autosave-status';
            return;
        }
        $statusEl.innerHTML =
            `<span class="material-symbols-outlined fn-autosave-icon">${cfg.icon}</span>`;
        $statusEl.className = `fn-autosave-status ${cfg.cls}`;
    }

})();
