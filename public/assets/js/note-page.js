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

        if (isCreateMode) {
            // Intercept prependNoteCard to grab the newly created note's ID
            const _origPrepend = window.prependNoteCard;
            window.prependNoteCard = function (note) {
                _createdNoteId = note?.id ?? null;
                // Don't actually prepend a card since we're navigating away
                // (the card will be rendered by the dashboard on next load)
            };
        }

        window.closeNewNoteModal = function () {
            // Identical teardown as original closeNewNoteModal
            if (typeof autoSaveCancel      === 'function') autoSaveCancel();
            if (typeof closeImgPicker      === 'function') closeImgPicker();
            if (typeof closeSlashImgPicker === 'function') closeSlashImgPicker();

            if (isCreateMode && _createdNoteId) {
                // After creating → go to the new note's edit page
                window.location.href = `/notes/${_createdNoteId}/edit`;
            } else {
                // After editing (or cancelled create) → back to dashboard
                window.location.href = '/dashboard';
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

    }); // DOMContentLoaded

})();
