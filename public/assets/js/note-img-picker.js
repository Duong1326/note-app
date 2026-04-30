/**
 * note-img-picker.js – Inline image picker inside the note modal.
 * Renders as a normal block element above the title row (no fixed/absolute overlay).
 * Depends on: app.js (showToast)
 *
 * Public API:
 *   openImgPicker(anchor, onFile)  – show the picker; `anchor` is ignored (always inline)
 *   closeImgPicker()               – hide the picker
 */

(function () {
    'use strict';

    let _onFile = null;
    let _picker = null;  // #modalInlineImgPicker
    let _zone   = null;  // #modalInlinePickerZone
    let _input  = null;  // #modalInlinePickerInput

    /* ── Wire up the pre-existing DOM elements ─────────── */
    document.addEventListener('DOMContentLoaded', () => {
        _picker = document.getElementById('modalInlineImgPicker');
        _zone   = document.getElementById('modalInlinePickerZone');
        _input  = document.getElementById('modalInlinePickerInput');

        if (!_picker) return;

        // Click zone → open file browser
        _zone.addEventListener('click', () => _input.click());

        // File selected via input
        _input.addEventListener('change', () => {
            const f = _input.files[0];
            _input.value = '';
            if (f) _handleFile(f);
        });

        // Drag & drop on zone
        _zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            _zone.classList.add('drag-over');
        });
        _zone.addEventListener('dragleave', (e) => {
            if (!_zone.contains(e.relatedTarget)) _zone.classList.remove('drag-over');
        });
        _zone.addEventListener('drop', (e) => {
            e.preventDefault();
            _zone.classList.remove('drag-over');
            const f = e.dataTransfer.files[0];
            if (f) _handleFile(f);
        });

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !_picker.classList.contains('d-none')) {
                closeImgPicker();
            }
        });
    });

    /* ── File validation ───────────────────────────────── */
    function _handleFile(file) {
        if (!file.type.startsWith('image/')) {
            if (typeof showToast === 'function') showToast('Chỉ hỗ trợ file ảnh', 'error');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            if (typeof showToast === 'function') showToast('Ảnh vượt quá 10 MB', 'error');
            return;
        }
        // Save callback BEFORE closeImgPicker() nulls _onFile
        const cb = _onFile;
        closeImgPicker();
        if (typeof cb === 'function') cb(file);
    }

    /* ── Public API ────────────────────────────────────── */

    /**
     * Show the inline picker.
     * @param {*}        _anchor  ignored – picker is always above the title row
     * @param {Function} onFile   called with the selected File object
     */
    window.openImgPicker = function (_anchor, onFile) {
        _onFile = onFile;

        if (!_picker) {
            // DOM not ready yet – retry after DOMContentLoaded wires things up
            _picker = document.getElementById('modalInlineImgPicker');
            _zone   = document.getElementById('modalInlinePickerZone');
            _input  = document.getElementById('modalInlinePickerInput');
            if (!_picker) return;
        }

        // Toggle: if already visible, close it
        if (!_picker.classList.contains('d-none')) {
            closeImgPicker();
            return;
        }

        _picker.classList.remove('d-none');
        // Animate in
        requestAnimationFrame(() => _picker.classList.add('fn-visible'));
    };

    window.closeImgPicker = function () {
        if (!_picker) return;
        _picker.classList.remove('fn-visible');
        setTimeout(() => {
            if (_picker && !_picker.classList.contains('fn-visible')) {
                _picker.classList.add('d-none');
            }
        }, 160);
        _onFile = null;
    };

    // closeSlashImgPicker is a no-op now (slash picker is inline in the editor)
    window.closeSlashImgPicker = function () {};

})();
