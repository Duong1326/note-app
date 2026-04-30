/**
 * auto-save.js – Automatic note saving while editing.
 *
 * When the edit modal is open, watches the title + content fields.
 * After the user stops typing for AUTO_SAVE_DELAY ms, silently calls
 * PUT /notes/{id} and shows a subtle "Đã lưu" indicator.
 *
 * Only runs for EXISTING notes (_editingNoteId !== null).
 * New notes are not auto-saved (they need an ID from the first POST first).
 *
 * Depends on:
 *   app.js       (apiFetch, showToast)
 *   note-modal.js (_editingNoteId, _editLockToken, getEditorContent)
 *   note-cards.js (patchNoteCard, moveCardToTopOfUnpinned)
 */

(function () {
    'use strict';

    /* ── Config ──────────────────────────────────────── */
    const AUTO_SAVE_DELAY = 1500;   // ms after user stops typing

    /* ── State ───────────────────────────────────────── */
    let _autoSaveTimer   = null;
    let _isSaving        = false;
    let _lastSavedTitle  = null;
    let _lastSavedContent = null;
    let _lastSavedLabels  = null;

    /* ── Status indicator element ────────────────────── */
    let $statusEl = null;

    /* ── Boot: attach listeners after modal DOM exists ── */
    document.addEventListener('DOMContentLoaded', () => {
        const titleInput   = document.getElementById('modalNoteTitle');
        const contentEl    = document.getElementById('modalNoteContent');
        const labelsChips  = document.getElementById('modalLabelsChips');

        if (titleInput)  titleInput.addEventListener('input',   _scheduleAutoSave);
        if (contentEl)   contentEl.addEventListener('input',    _scheduleAutoSave);
        if (labelsChips) labelsChips.addEventListener('change', _scheduleAutoSave);

        // Build the status element (injected into the modal footer toolbar area)
        _buildStatusEl();
    });

    /* ── Public API ──────────────────────────────────── */

    /**
     * Called by note-modal.js when the edit modal opens for an existing note.
     * Stores the baseline so we only auto-save on actual changes.
     */
    window.autoSaveReset = function (title, content, labelIds) {
        clearTimeout(_autoSaveTimer);
        _isSaving         = false;
        _lastSavedTitle   = title;
        _lastSavedContent = content;
        _lastSavedLabels  = (labelIds ?? []).map(String).sort().join(',');
        _setStatus('idle');
    };

    /**
     * Called by note-modal.js when the modal closes (manual save or cancel).
     * Cancels any pending auto-save.
     */
    window.autoSaveCancel = function () {
        clearTimeout(_autoSaveTimer);
        _isSaving = false;
        _setStatus('idle');
    };

    /* ── Internal ────────────────────────────────────── */

    function _scheduleAutoSave() {
        // Only auto-save when editing an existing note
        if (typeof _editingNoteId === 'undefined' || !_editingNoteId) return;

        clearTimeout(_autoSaveTimer);
        _setStatus('pending');
        _autoSaveTimer = setTimeout(_doAutoSave, AUTO_SAVE_DELAY);
    }

    async function _doAutoSave() {
        if (_isSaving) return;

        // Read current values
        const title   = document.getElementById('modalNoteTitle')?.value?.trim() ?? '';
        const content = (typeof getEditorContent === 'function') ? getEditorContent() : '';
        const labelIds = [...document.querySelectorAll('input[name="label_ids[]"]:checked')]
            .map(cb => cb.value);
        const labelsKey = labelIds.map(String).sort().join(',');

        // Skip if nothing changed since last save
        if (
            title   === _lastSavedTitle   &&
            content === _lastSavedContent &&
            labelsKey === _lastSavedLabels
        ) {
            _setStatus('idle');
            return;
        }

        // Require a non-empty title before saving
        if (!title) {
            _setStatus('idle');
            return;
        }

        _isSaving = true;
        _setStatus('saving');

        try {
            const noteId    = _editingNoteId;
            const lockToken = (typeof _editLockToken !== 'undefined') ? _editLockToken : null;

            const body = new URLSearchParams({ title, content });
            labelIds.forEach(id => body.append('label_ids[]', id));

            const headers = lockToken ? { 'X-Note-Token': lockToken } : {};
            const res  = await apiFetch(`/notes/${noteId}`, 'PUT', body, headers);
            const data = await res.json();

            if (!res.ok) {
                // Don't spam error toasts on auto-save — just reset status silently
                _setStatus('idle');
                return;
            }

            // Update baseline
            _lastSavedTitle   = title;
            _lastSavedContent = content;
            _lastSavedLabels  = labelsKey;

            // Patch the card on the dashboard (visible behind the modal)
            if (typeof patchNoteCard === 'function') {
                patchNoteCard(noteId, data.note);
            }
            const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
            if (col && typeof moveCardToTopOfUnpinned === 'function') {
                moveCardToTopOfUnpinned(col);
            }

            _setStatus('saved');

            // Fade back to idle after 2 s
            setTimeout(() => _setStatus('idle'), 2000);

        } catch {
            // Network error — fail silently (user will see stale data, not a crash)
            _setStatus('idle');
        } finally {
            _isSaving = false;
        }
    }

    /* ── Status indicator ────────────────────────────── */

    function _buildStatusEl() {
        // Inject into the modal footer toolbar placeholder
        const toolbar = document.querySelector('#newNoteModal .fn-modal-toolbar');
        if (!toolbar || toolbar.querySelector('.fn-autosave-status')) return;

        $statusEl = document.createElement('span');
        $statusEl.className = 'fn-autosave-status';
        $statusEl.setAttribute('aria-live', 'polite');
        toolbar.appendChild($statusEl);
    }

    const _statusConfig = {
        idle:    { icon: '',              text: '',             cls: '' },
        pending: { icon: 'edit',          text: 'Đang soạn…',  cls: 'pending' },
        saving:  { icon: 'cloud_sync',    text: 'Đang lưu…',   cls: 'saving'  },
        saved:   { icon: 'cloud_done',    text: 'Đã lưu',      cls: 'saved'   },
    };

    function _setStatus(state) {
        if (!$statusEl) {
            // Attempt late initialisation (modal might have been added to DOM after boot)
            _buildStatusEl();
            if (!$statusEl) return;
        }
        const cfg = _statusConfig[state] ?? _statusConfig.idle;
        if (!cfg.text) {
            $statusEl.innerHTML = '';
            $statusEl.className = 'fn-autosave-status';
            return;
        }
        $statusEl.innerHTML =
            `<span class="material-symbols-outlined fn-autosave-icon">${cfg.icon}</span>${cfg.text}`;
        $statusEl.className = `fn-autosave-status ${cfg.cls}`;
    }

})();
