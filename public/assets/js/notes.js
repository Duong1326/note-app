/**
 * notes.js – Notes dashboard orchestrator.
 * Handles: view toggle, AJAX actions (delete, pin/unpin), and event bindings.
 *
 * Depends on: app.js       (apiFetch, showToast, getCsrfToken)
 *             note-lock.js  (requireUnlock, getLockToken, clearLockToken)
 *             note-cards.js (removeNoteCard, patchPinCard, moveCardAfterPin)
 *             note-modal.js (openNewNoteModal, openEditNoteModal, closeNewNoteModal, submitNoteForm)
 *             note-attachments.js (renderPendingPreviews, showAttachmentSection)
 */

// ═══════════════════════════════════════════════════
// View Toggle (grid / list)
// ═══════════════════════════════════════════════════

function setNotesView(view) {
    const container = document.getElementById('notesContainer');
    const btnGrid = document.getElementById('btnGridView');
    const btnList = document.getElementById('btnListView');
    if (!container) return;

    const isList = view === 'list';
    container.classList.toggle('fn-view-list', isList);
    btnList?.classList.toggle('active', isList);
    btnGrid?.classList.toggle('active', !isList);
    localStorage.setItem('notesView', view);
}

// ═══════════════════════════════════════════════════
// AJAX Actions (delete, pin/unpin)
// ═══════════════════════════════════════════════════

async function deleteNoteAjax(noteId) {
    requireUnlock(noteId, async () => {
        if (!confirm('Bạn có chắc chắn muốn xóa ghi chú này?')) return;
        const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
        try {
            const token = getLockToken(noteId);
            const headers = token ? { 'X-Note-Token': token } : {};
            const res = await apiFetch(`/notes/${noteId}`, 'DELETE', null, headers);
            if (!res.ok) throw new Error('Delete failed');
            clearLockToken(noteId); // one-time: action done
            if (col) removeNoteCard(col);
        } catch (err) {
            if (col) { col.style.opacity = ''; col.style.transform = ''; col.style.pointerEvents = ''; }
            showToast(err.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

async function togglePinAjax(noteId, currentlyPinned) {
    requireUnlock(noteId, async () => {
        const url = currentlyPinned ? `/notes/${noteId}/unpin` : `/notes/${noteId}/pin`;
        try {
            const token = getLockToken(noteId);
            const headers = token ? { 'X-Note-Token': token } : {};
            const res = await apiFetch(url, 'POST', null, headers);
            if (!res.ok) throw new Error('Failed to toggle pin state');
            const { is_pinned: isPinned } = await res.json();
            clearLockToken(noteId); // one-time: action done
            const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
            if (col) {
                patchPinCard(col, noteId, isPinned);
                moveCardAfterPin(col, isPinned);
            }
        } catch (err) {
            showToast(err.message || 'Có lỗi xảy ra', 'error');
        }
    });
}

// ═══════════════════════════════════════════════════
// Event Bindings
// ═══════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Restore last used view preference
    setNotesView(localStorage.getItem('notesView') || 'grid');

    // Keep dropdown card above sibling cards
    document.addEventListener('shown.bs.dropdown', e => e.target.closest('.note-col')?.classList.add('dropdown-open'));
    document.addEventListener('hidden.bs.dropdown', e => e.target.closest('.note-col')?.classList.remove('dropdown-open'));

    // Note form submit
    document.getElementById('createNoteForm')?.addEventListener('submit', e => {
        e.preventDefault();
        submitNoteForm();
    });

    // Close modal when clicking the overlay backdrop
    document.getElementById('newNoteModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeNewNoteModal();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
        // Slash menu takes priority when visible
        if (_slashMenuVisible && handleSlashMenuKeydown(e)) return;

        if (e.key === 'Escape') {
            if (_slashMenuVisible) { hideSlashMenu(); return; }
            closeNewNoteModal();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            const modal = document.getElementById('newNoteModal');
            if (modal?.classList.contains('show')) {
                e.preventDefault();
                submitNoteForm();
            }
        }
    });

    // Content editor: slash command trigger + filter
    const contentEditor = document.getElementById('modalNoteContent');
    if (contentEditor) {
        contentEditor.addEventListener('input', () => {
            if (_slashMenuVisible) {
                // Menu already open → filter as user types
                handleSlashMenuInput();
            } else {
                // Check if "/" was just typed → open menu
                const sel = window.getSelection();
                if (!sel.rangeCount) return;
                const node = sel.focusNode;
                if (node && node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent;
                    const pos = sel.focusOffset;
                    if (pos > 0 && text[pos - 1] === '/') {
                        showSlashMenu();
                    }
                }
            }
        });

        // Hide slash menu when clicking outside
        document.addEventListener('mousedown', e => {
            if (_slashMenuVisible && !e.target.closest('.fn-slash-menu') && !e.target.closest('#modalNoteContent')) {
                hideSlashMenu();
            }
        });
    }

    // File input change — validate size and queue for upload
    const fileInput = document.getElementById('attachmentFileInput');
    fileInput?.addEventListener('change', () => {
        const files = [...fileInput.files].filter(f => f.size <= 10 * 1024 * 1024);
        if (files.length < fileInput.files.length) {
            showToast('Một số ảnh vượt quá 10 MB và đã bị bỏ qua', 'error');
        }
        _pendingFiles = [..._pendingFiles, ...files];
        renderPendingPreviews();
        fileInput.value = ''; // reset so the same file can be picked again
    });

    // Drag-and-drop on the drop zone
    const dropzone = document.getElementById('attachmentDropzone');
    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone?.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        const files = [...e.dataTransfer.files]
            .filter(f => f.type.startsWith('image/') && f.size <= 10 * 1024 * 1024);
        _pendingFiles = [..._pendingFiles, ...files];
        renderPendingPreviews();
    });

    // Click-to-edit: clicking anywhere on a note card opens the edit modal
    document.addEventListener('click', e => {
        const card = e.target.closest('.fn-note-card');
        if (!card) return;

        // Ignore clicks on interactive elements inside the card
        if (e.target.closest('.dropdown') || e.target.closest('button') || e.target.closest('a')) return;

        const col = card.closest('.note-col');
        const noteId = col?.dataset.noteId;
        const editBtn = card.querySelector('.dropdown-item[onclick*="openEditNoteModal"]');
        if (!editBtn || !noteId) return;

        requireUnlock(noteId, () => openEditNoteModal(editBtn));
    });
});
