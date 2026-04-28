/**
 * note-slash-menu.js – Slash command popup for the note content editor.
 * Depends on: note-attachments.js (compressImage)
 *             app.js (escapeHtml)
 */

// Inline content images (separate from thumbnail _pendingFiles)
let _inlineContentFiles = [];

/** Strip Vietnamese diacritics for fuzzy matching (e.g. "hinh" matches "Hình") */
function _removeDiacritics(str) {
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D');
}

// ═══════════════════════════════════════════════════
// Slash Menu Configuration
// ═══════════════════════════════════════════════════

const SLASH_COMMANDS = [
    {
        id: 'heading1',
        icon: 'format_h1',
        label: 'Heading 1',
        description: 'Tiêu đề cấp 1',
        shortcut: '#',
        tag: 'h1',
    },
    {
        id: 'heading2',
        icon: 'format_h2',
        label: 'Heading 2',
        description: 'Tiêu đề cấp 2',
        shortcut: '##',
        tag: 'h2',
    },
    {
        id: 'heading3',
        icon: 'format_h3',
        label: 'Heading 3',
        description: 'Tiêu đề cấp 3',
        shortcut: '###',
        tag: 'h3',
    },
    {
        id: 'image',
        icon: 'image',
        label: 'Hình ảnh',
        description: 'Chèn ảnh vào nội dung',
        shortcut: '',
    },
    {
        id: 'divider',
        icon: 'horizontal_rule',
        label: 'Đường kẻ ngang',
        description: 'Thêm đường phân cách',
        shortcut: '---',
    },
    {
        id: 'bullet_list',
        icon: 'format_list_bulleted',
        label: 'Danh sách',
        description: 'Danh sách dấu chấm',
        shortcut: '-',
    },
    {
        id: 'numbered_list',
        icon: 'format_list_numbered',
        label: 'Danh sách số',
        description: 'Danh sách đánh số',
        shortcut: '1.',
    },
];

// ═══════════════════════════════════════════════════
// State
// ═══════════════════════════════════════════════════

let _slashMenuVisible = false;
let _slashMenuIndex = 0;         // Keyboard-highlighted index
let _slashFilterText = '';        // Text typed after "/" for filtering
let _slashRange = null;           // Saved selection range when "/" was typed

// ═══════════════════════════════════════════════════
// Menu DOM
// ═══════════════════════════════════════════════════

function _getSlashMenu() {
    return document.getElementById('slashCommandMenu');
}

function _buildMenuItems(filter) {
    const menu = _getSlashMenu();
    if (!menu) return [];

    const list = menu.querySelector('.fn-slash-list');
    if (!list) return [];

    const q = _removeDiacritics(filter.toLowerCase());
    const filtered = SLASH_COMMANDS.filter(cmd =>
        _removeDiacritics(cmd.label.toLowerCase()).includes(q)
    );

    list.innerHTML = filtered.map((cmd, idx) => `
        <div class="fn-slash-item${idx === _slashMenuIndex ? ' active' : ''}"
             data-cmd-id="${cmd.id}"
             onmousedown="executeSlashCommand('${cmd.id}')">
            <div class="fn-slash-item-icon">
                <span class="material-symbols-outlined">${cmd.icon}</span>
            </div>
            <div class="fn-slash-item-info">
                <span class="fn-slash-item-label">${cmd.label}</span>
                <span class="fn-slash-item-desc">${cmd.description}</span>
            </div>
            ${cmd.shortcut ? `<span class="fn-slash-item-shortcut">${cmd.shortcut}</span>` : ''}
        </div>
    `).join('');

    return filtered;
}

// ═══════════════════════════════════════════════════
// Show / Hide / Position
// ═══════════════════════════════════════════════════

function showSlashMenu() {
    const menu = _getSlashMenu();
    if (!menu) return;

    _slashMenuVisible = true;
    _slashMenuIndex = 0;
    _slashFilterText = '';

    // Save the current selection so we can restore it when executing a command
    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
        _slashRange = sel.getRangeAt(0).cloneRange();
    }

    _buildMenuItems('');
    menu.classList.remove('d-none');

    // Position the menu near the caret
    _positionSlashMenu();
}

function hideSlashMenu() {
    const menu = _getSlashMenu();
    if (menu) menu.classList.add('d-none');
    _slashMenuVisible = false;
    _slashFilterText = '';
    _slashRange = null;
}

function _positionSlashMenu() {
    const menu = _getSlashMenu();
    const editor = document.getElementById('modalNoteContent');
    if (!menu || !editor) return;

    const sel = window.getSelection();
    if (!sel.rangeCount) return;

    const range = sel.getRangeAt(0);
    const rect = range.getBoundingClientRect();
    const editorRect = editor.getBoundingClientRect();

    // Position below the caret, relative to the editor
    let top = rect.bottom - editorRect.top + 6;
    let left = rect.left - editorRect.left;

    // Keep within editor bounds
    left = Math.max(0, Math.min(left, editorRect.width - 280));

    menu.style.top = top + 'px';
    menu.style.left = left + 'px';
}

// ═══════════════════════════════════════════════════
// Keyboard Navigation
// ═══════════════════════════════════════════════════

function handleSlashMenuKeydown(e) {
    if (!_slashMenuVisible) return false;

    const q = _removeDiacritics(_slashFilterText.toLowerCase());
    const filtered = SLASH_COMMANDS.filter(cmd =>
        _removeDiacritics(cmd.label.toLowerCase()).includes(q)
    );

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        _slashMenuIndex = (_slashMenuIndex + 1) % filtered.length;
        _buildMenuItems(_slashFilterText);
        return true;
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        _slashMenuIndex = (_slashMenuIndex - 1 + filtered.length) % filtered.length;
        _buildMenuItems(_slashFilterText);
        return true;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        if (filtered[_slashMenuIndex]) {
            executeSlashCommand(filtered[_slashMenuIndex].id);
        }
        return true;
    }
    if (e.key === 'Escape') {
        e.preventDefault();
        hideSlashMenu();
        return true;
    }
    if (e.key === 'Backspace') {
        if (_slashFilterText.length > 0) {
            _slashFilterText = _slashFilterText.slice(0, -1);
            _slashMenuIndex = 0;
            _buildMenuItems(_slashFilterText);
        } else {
            // Backspace on "/" itself → close menu
            hideSlashMenu();
        }
        return false; // Let the default backspace still work
    }

    return false;
}

function handleSlashMenuInput(e) {
    if (!_slashMenuVisible) return;

    // After input, check what's typed after the "/" trigger
    const editor = document.getElementById('modalNoteContent');
    const sel = window.getSelection();
    if (!sel.rangeCount || !editor) return;

    // Get text content of current text node
    const node = sel.focusNode;
    if (!node || node.nodeType !== Node.TEXT_NODE) {
        hideSlashMenu();
        return;
    }

    const text = node.textContent;
    const caretPos = sel.focusOffset;

    // Find the last "/" before the caret
    const slashIdx = text.lastIndexOf('/', caretPos);
    if (slashIdx === -1) {
        hideSlashMenu();
        return;
    }

    _slashFilterText = text.substring(slashIdx + 1, caretPos);
    _slashMenuIndex = 0;

    // Keep the saved range up-to-date as user types filter text
    _slashRange = sel.getRangeAt(0).cloneRange();

    const filtered = _buildMenuItems(_slashFilterText);

    if (filtered.length === 0) {
        hideSlashMenu();
    }
}

// ═══════════════════════════════════════════════════
// Command Execution
// ═══════════════════════════════════════════════════

function executeSlashCommand(cmdId) {
    // Restore focus and selection in the editor before modifying content
    _restoreEditorSelection();

    // Remove the "/" trigger text and any filter text from the content
    _removeSlashText();
    hideSlashMenu();

    switch (cmdId) {
        case 'heading1':
            _insertHeadingBlock('h1');
            break;
        case 'heading2':
            _insertHeadingBlock('h2');
            break;
        case 'heading3':
            _insertHeadingBlock('h3');
            break;
        case 'image':
            _insertImageBlock();
            break;
        case 'divider':
            _insertDividerBlock();
            break;
        case 'bullet_list':
            _insertListBlock('ul');
            break;
        case 'numbered_list':
            _insertListBlock('ol');
            break;
    }
}

function _removeSlashText() {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;

    const sel = window.getSelection();
    if (!sel.rangeCount) return;

    const node = sel.focusNode;
    if (!node || node.nodeType !== Node.TEXT_NODE) return;

    const text = node.textContent;
    const caretPos = sel.focusOffset;
    const slashIdx = text.lastIndexOf('/', caretPos);

    if (slashIdx !== -1) {
        // Remove "/" and the filter text
        node.textContent = text.substring(0, slashIdx) + text.substring(caretPos);

        // Restore caret position
        const newPos = Math.min(slashIdx, node.textContent.length);
        const range = document.createRange();
        range.setStart(node, newPos);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);

        // Update saved range so _insertBlockAtCaret uses correct position
        _slashRange = range.cloneRange();
    }
}

/**
 * Restore the editor focus and the saved selection range.
 * Called before executing a slash command to ensure caret is in the right place.
 */
function _restoreEditorSelection() {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;
    editor.focus();

    if (_slashRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(_slashRange);
    }
}

// ═══════════════════════════════════════════════════
// Block Insertion: Image
// ═══════════════════════════════════════════════════

function _insertImageBlock() {
    // Save current caret position before file picker steals focus
    const savedRange = _slashRange ? _slashRange.cloneRange() : null;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/jpg,image/png,image/gif,image/webp';
    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            showToast('Ảnh vượt quá 10 MB', 'error');
            return;
        }

        const url = URL.createObjectURL(file);

        // Create the inline image frame
        const wrapper = document.createElement('div');
        wrapper.className = 'fn-content-image-block';
        wrapper.contentEditable = 'false';

        wrapper.innerHTML = `
            <img src="${url}" alt="Inline image" class="fn-content-image">
            <button type="button" class="fn-content-image-remove" title="Xóa ảnh"
                    onclick="removeInlineImage(this)">
                <span class="material-symbols-outlined">close</span>
            </button>
        `;

        // Store file reference for upload later (separate from thumbnail system)
        wrapper.dataset.pendingFile = 'true';
        wrapper._file = file;
        _inlineContentFiles.push({ file, blobUrl: url, wrapper });

        // Restore editor focus and caret position
        const editor = document.getElementById('modalNoteContent');
        if (editor) editor.focus();
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }

        // Insert the image block and move cursor below it
        _insertBlockAtCaret(wrapper);

        // Ensure editor stays focused after insertion
        if (editor) editor.focus();
    };
    input.click();
}

function removeInlineImage(btn) {
    const block = btn.closest('.fn-content-image-block');
    if (!block) return;

    // Remove from inline content files
    _inlineContentFiles = _inlineContentFiles.filter(item => item.wrapper !== block);

    block.remove();

    // Focus back on editor
    document.getElementById('modalNoteContent')?.focus();
}

// ═══════════════════════════════════════════════════
// Block Insertion: Divider
// ═══════════════════════════════════════════════════

function _insertDividerBlock() {
    const hr = document.createElement('div');
    hr.className = 'fn-content-divider';
    hr.contentEditable = 'false';
    hr.innerHTML = '<hr>';
    _insertBlockAtCaret(hr);
}

// ═══════════════════════════════════════════════════
// Block Insertion: Heading
// ═══════════════════════════════════════════════════

function _insertHeadingBlock(tag = 'h2') {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;

    // Insert a heading element with the specified tag
    const h = document.createElement(tag);
    h.className = `fn-content-heading fn-content-${tag}`;
    h.innerHTML = '&#8203;'; // zero-width space for reliable cursor placement

    // Insert block without auto-cursor (we handle cursor ourselves)
    _insertBlockAtCaret(h, true);

    // Add a line break after the heading for continued typing
    const br = document.createElement('br');
    h.after(br);

    // Place cursor inside the heading
    const range = document.createRange();
    range.selectNodeContents(h);
    range.collapse(false); // end of content
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
}

// ═══════════════════════════════════════════════════
// Block Insertion: List
// ═══════════════════════════════════════════════════

function _insertListBlock(tag = 'ul') {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;

    const list = document.createElement(tag);
    list.className = 'fn-content-list';

    const li = document.createElement('li');
    li.innerHTML = '&#8203;'; // zero-width space for reliable cursor placement
    list.appendChild(li);

    // Insert block without auto-cursor (we handle cursor ourselves)
    _insertBlockAtCaret(list, true);

    // Add a line break after the list for continued typing
    const br = document.createElement('br');
    list.after(br);

    // Place cursor inside the first list item
    const range = document.createRange();
    range.selectNodeContents(li);
    range.collapse(false);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
}

// ═══════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════

function _insertBlockAtCaret(element, skipCursorMove = false) {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;

    const sel = window.getSelection();
    if (!sel.rangeCount) {
        // Fallback: append at the end
        editor.appendChild(element);
        if (!skipCursorMove) _moveCursorAfterBlock(element, sel);
        return;
    }

    const range = sel.getRangeAt(0);
    range.collapse(false);

    // Clean up empty text nodes before inserting
    const container = range.startContainer;
    if (container.nodeType === Node.TEXT_NODE && container.textContent === '') {
        container.parentNode.replaceChild(element, container);
    } else {
        range.insertNode(element);
    }

    if (!skipCursorMove) _moveCursorAfterBlock(element, sel);
}

/**
 * Move cursor after the inserted block element, adding a <br> for continued typing.
 */
function _moveCursorAfterBlock(element, sel) {
    // Add a new line after the block for continued typing
    const br = document.createElement('br');
    element.after(br);

    // Move caret after the block
    const newRange = document.createRange();
    newRange.setStartAfter(br);
    newRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(newRange);
}

/**
 * Get clean HTML content from the editor.
 */
function getEditorContent() {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return '';
    return editor.innerHTML;
}

/**
 * Set content in the editor (used when loading for edit mode).
 */
function setEditorContent(html) {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;
    editor.innerHTML = html || '';
}

/**
 * Clear the editor content.
 */
function clearEditorContent() {
    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;
    editor.innerHTML = '';
    _inlineContentFiles = [];
}

/**
 * Upload all inline content images to the server.
 * Replaces blob URLs in the content HTML with real Cloudinary URLs.
 * Call this BEFORE reading getEditorContent() for submission.
 */
async function uploadInlineContentImages(noteId) {
    if (_inlineContentFiles.length === 0) return;

    const editor = document.getElementById('modalNoteContent');
    if (!editor) return;

    const uploads = _inlineContentFiles.map(async (item) => {
        try {
            const compressed = typeof compressImage === 'function'
                ? await compressImage(item.file)
                : item.file;

            const formData = new FormData();
            formData.append('image', compressed);
            formData.append('_token', getCsrfToken());

            const res = await apiFetch(`/notes/${noteId}/attachments`, 'POST', formData);
            const data = await res.json();

            if (data.success) {
                // Replace the blob URL with the real Cloudinary URL in the DOM
                const img = item.wrapper.querySelector('.fn-content-image');
                if (img) {
                    img.src = data.attachment.url;
                }
                URL.revokeObjectURL(item.blobUrl);
                return data.attachment;
            }
            showToast(data.message || 'Tải ảnh nội dung thất bại', 'error');
            return null;
        } catch {
            showToast('Lỗi kết nối khi tải ảnh nội dung', 'error');
            return null;
        }
    });

    await Promise.all(uploads);
    _inlineContentFiles = [];
}
