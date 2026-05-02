/**
 * labels.js – Sidebar and modal label management via AJAX.
 */

// ═══════════════════════════════════════════════════
// Sidebar Label Form
// ═══════════════════════════════════════════════════

function toggleAddLabelForm(forceClose = false) {
    const btn = document.getElementById('sidebarAddBtn');
    const form = document.getElementById('sidebarLabelAddForm');
    const input = document.getElementById('newSidebarLabelInput');

    if (forceClose || !form.classList.contains('d-none')) {
        form.classList.add('d-none');
        btn.classList.remove('d-none');
        input.value = '';
    } else {
        form.classList.remove('d-none');
        btn.classList.add('d-none');
        input.focus();
    }
}

async function onSidebarLabelBlur() {
    const input = document.getElementById('newSidebarLabelInput');
    const name = input?.value.trim();
    if (name) {
        await createLabel();
    } else {
        toggleAddLabelForm(true);
    }
}

async function createLabel() {
    const input = document.getElementById('newSidebarLabelInput');
    const name = input.value.trim();
    if (!name) return toggleAddLabelForm();

    try {
        const body = new URLSearchParams({ name });
        const res = await apiFetch(window.FN_LABEL_STORE_URL, 'POST', body);
        const data = await res.json();

        if (!res.ok) {
            const msg = data.errors?.name?.[0] || data.message || 'Có lỗi xảy ra';
            showToast(msg, 'error');
            return;
        }

        const label = data.data;
        appendLabelItem(label);   // update sidebar list
        toggleAddLabelForm();     // hide form
        addLabelCheckbox(label);  // sync modal checkboxes

    } catch {
        showToast('Lỗi kết nối', 'error');
    }
}

// ═══════════════════════════════════════════════════
// Sidebar Label CRUD
// ═══════════════════════════════════════════════════

async function deleteLabel(labelId) {
    if (!confirm('Bạn có chắc chắn muốn xóa nhãn này?')) return;

    const row = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);

    try {
        const res = await apiFetch(`/labels/${labelId}`, 'DELETE');
        if (!res.ok) throw new Error('Failed to delete label');

        if (row) {
            row.style.transition = 'opacity 0.3s ease, margin-top 0.3s ease';
            row.style.opacity = '0';
            row.style.marginTop = `-${row.offsetHeight}px`;
            setTimeout(() => row.remove(), 320);
        }

        // Remove checkbox from note modal
        document.getElementById(`modal_label_${labelId}`)
            ?.closest('.fn-checkbox-label')?.remove();

        // Fade-remove badges from visible note cards
        document.querySelectorAll(`.fn-label-badge[data-label-id="${labelId}"]`).forEach(badge => {
            badge.style.transition = 'opacity 0.2s';
            badge.style.opacity = '0';
            setTimeout(() => {
                const parent = badge.parentNode;
                badge.remove();
                if (parent && parent.children.length === 0) {
                    parent.remove(); // remove .fn-note-labels if now empty
                }
            }, 200);
        });

        // Remove deleted label id from edit-button data attributes
        document.querySelectorAll('.dropdown-item[data-labels]').forEach(btn => {
            try {
                let arr = JSON.parse(btn.getAttribute('data-labels') || '[]');
                arr = arr.filter(id => id != labelId);
                btn.setAttribute('data-labels', JSON.stringify(arr));
            } catch { /* ignore parse errors */ }
        });

    } catch (err) {
        if (row) { row.style.opacity = ''; row.style.marginTop = ''; }
        showToast(err.message || 'Có lỗi xảy ra', 'error');
    }
}

async function saveRenameLabel(labelId) {
    const row = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);
    const input = row?.querySelector('.fn-sidebar-label-input');
    const newName = input?.value.trim();

    if (!newName) return cancelRenameLabel(labelId);

    try {
        const body = new URLSearchParams({ name: newName, _method: 'PUT' });
        const res = await apiFetch(`/labels/${labelId}`, 'POST', body);
        const data = await res.json();

        if (!res.ok) {
            const msg = data.errors?.name?.[0] || data.message || 'Có lỗi xảy ra';
            showToast(msg, 'error');
            return;
        }

        // Update sidebar display name
        row.querySelector('.fn-sidebar-label-name').textContent = data.data.name;
        cancelRenameLabel(labelId);

        // Sync modal checkbox label text
        const cbLabel = document.querySelector(`label[for="modal_label_${labelId}"] .fn-checkbox-text`);
        if (cbLabel) cbLabel.textContent = data.data.name;

        // Sync badge text on all visible note cards
        document.querySelectorAll(`.fn-label-badge[data-label-id="${labelId}"]`).forEach(badge => {
            badge.textContent = data.data.name;
        });

    } catch {
        showToast('Lỗi kết nối', 'error');
    }
}

// ═══════════════════════════════════════════════════
// DOM Helpers
// ═══════════════════════════════════════════════════

function appendLabelItem(label) {
    const list = document.getElementById('sidebarLabelsList');
    if (!list) return;

    const html = `
        <div class="fn-sidebar-label-item" data-label-id="${label.id}">
            <div class="fn-sidebar-label-view">
                <div class="fn-sidebar-label-info" onclick="filterNotesByLabel(${label.id}, '${escapeAttr(label.name)}')" style="cursor:pointer;">
                    <span class="material-symbols-outlined">sell</span>
                    <span class="fn-sidebar-label-name">${escapeHtml(label.name)}</span>
                </div>
                <div class="fn-sidebar-label-actions">
                    <button onclick="startRenameLabel(${label.id})" title="Đổi tên">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button onclick="deleteLabel(${label.id})" title="Xóa">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
            <div class="fn-sidebar-label-edit d-none">
                <input type="text" class="fn-sidebar-label-input" value="${escapeAttr(label.name)}"
                    onkeydown="if(event.key==='Enter')saveRenameLabel(${label.id});if(event.key==='Escape')cancelRenameLabel(${label.id});"
                    onblur="saveRenameLabel(${label.id})">
            </div>
        </div>`;

    list.insertAdjacentHTML('beforeend', html);
}

function startRenameLabel(labelId) {
    const row = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);
    if (!row) return;

    row.querySelector('.fn-sidebar-label-view').classList.add('d-none');
    const editDiv = row.querySelector('.fn-sidebar-label-edit');
    editDiv.classList.remove('d-none');
    const input = editDiv.querySelector('.fn-sidebar-label-input');
    input.focus();
    input.select();
}

function cancelRenameLabel(labelId) {
    const row = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);
    if (!row) return;

    row.querySelector('.fn-sidebar-label-view').classList.remove('d-none');
    row.querySelector('.fn-sidebar-label-edit').classList.add('d-none');

    // Reset input to current display name
    const name = row.querySelector('.fn-sidebar-label-name').textContent;
    row.querySelector('.fn-sidebar-label-input').value = name;
}

function addLabelCheckbox(label) {
    const chipsContainer = document.getElementById('modalLabelsChips');
    if (!chipsContainer) return;
    if (document.getElementById(`modal_label_${label.id}`)) return; // already exists

    const chip = document.createElement('label');
    chip.className = 'fn-checkbox-label';
    chip.setAttribute('for', `modal_label_${label.id}`);
    chip.innerHTML = `
        <input type="checkbox" name="label_ids[]" id="modal_label_${label.id}" value="${label.id}" class="fn-checkbox-input">
        <span class="fn-checkbox-box">
            <span class="material-symbols-outlined check-icon">check</span>
        </span>
        <span class="fn-checkbox-text">${escapeHtml(label.name)}</span>
    `;
    chipsContainer.appendChild(chip);
}

// ═══════════════════════════════════════════════════
// Modal Inline Label Creation
// ═══════════════════════════════════════════════════

function toggleModalAddLabelForm(forceClose = false) {
    const btn = document.getElementById('modalAddLabelBtn');
    const input = document.getElementById('modalNewLabelInput');
    if (!btn || !input) return;

    if (forceClose || !input.classList.contains('d-none')) {
        input.classList.add('d-none');
        btn.classList.remove('d-none');
        input.value = '';
    } else {
        input.classList.remove('d-none');
        btn.classList.add('d-none');
        input.focus();
    }
}

/**
 * Called on blur of the modal label input.
 * Saves the label if text is present; otherwise just closes the input.
 */
async function onModalLabelBlur() {
    const input = document.getElementById('modalNewLabelInput');
    const name = input?.value.trim();
    if (name) {
        await createLabelFromModal();
    } else {
        toggleModalAddLabelForm(true);
    }
}

async function createLabelFromModal() {
    const input = document.getElementById('modalNewLabelInput');
    const name = input.value.trim();
    if (!name) {
        toggleModalAddLabelForm(true);
        return;
    }

    try {
        const body = new URLSearchParams({ name });
        const res = await apiFetch(window.FN_LABEL_STORE_URL, 'POST', body);
        const data = await res.json();

        if (!res.ok) {
            const msg = data.errors?.name?.[0] || data.message || 'Có lỗi xảy ra';
            showToast(msg, 'error');
            return;
        }

        const label = data.data;
        appendLabelItem(label); // sync sidebar
        addLabelCheckbox(label); // sync modal

        // Auto-check the new label
        const checkbox = document.getElementById(`modal_label_${label.id}`);
        if (checkbox) checkbox.checked = true;

        toggleModalAddLabelForm(true);

    } catch {
        showToast('Lỗi kết nối', 'error');
    }
}

// ═══════════════════════════════════════════════════
// Label Filter (multi-select)
// ═══════════════════════════════════════════════════

/** Set of currently active label ids */
const _activeLabelIds = new Set();

/** Map of id → name for chip rendering */
const _activeLabelNames = new Map();

/**
 * Toggle a label in/out of the active filter set, then re-fetch notes.
 * Call with no arguments (or labelId=0) to clear all filters.
 */
async function filterNotesByLabel(labelId, labelName) {
    labelId = parseInt(labelId);

    if (!labelId) {
        // Clear all
        _activeLabelIds.clear();
        _activeLabelNames.clear();
    } else if (_activeLabelIds.has(labelId)) {
        // Deselect
        _activeLabelIds.delete(labelId);
        _activeLabelNames.delete(labelId);
    } else {
        // Select
        _activeLabelIds.add(labelId);
        _activeLabelNames.set(labelId, labelName || '');
    }

    // ── Update sidebar active state ──
    document.querySelectorAll('.fn-sidebar-label-item').forEach(item => {
        const id = parseInt(item.dataset.labelId);
        item.classList.toggle('fn-label-active', _activeLabelIds.has(id));
    });

    // ── Re-render chip bar ──
    _renderFilterChips();

    // ── Fetch filtered notes from server ──
    await _fetchFilteredNotes();
}

/** Remove one chip and re-fetch */
async function removeLabelFilter(labelId) {
    labelId = parseInt(labelId);
    _activeLabelIds.delete(labelId);
    _activeLabelNames.delete(labelId);

    const item = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);
    item?.classList.remove('fn-label-active');

    _renderFilterChips();
    await _fetchFilteredNotes();
}

/** Build URL and fetch notes, then render */
async function _fetchFilteredNotes() {
    const url = new URL(window.FN_FILTER_LABEL_URL, location.origin);
    _activeLabelIds.forEach(id => url.searchParams.append('label_ids[]', id));

    try {
        const res = await apiFetch(url.toString());
        const data = await res.json();
        _renderFilteredNotes(data.notes ?? []);
    } catch {
        showToast('Lỗi khi lọc ghi chú', 'error');
    }
}

/** Render the row of active-filter chips (or remove bar if none) */
function _renderFilterChips() {
    let bar = document.getElementById('fn-label-filter-bar');

    if (_activeLabelIds.size === 0) {
        bar?.remove();
        return;
    }

    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'fn-label-filter-bar';
        bar.className = 'fn-label-filter-bar';
        const header = document.querySelector('.fn-welcome ~ .row .d-flex.align-items-center.justify-content-between');
        header?.insertAdjacentElement('afterend', bar);
    }

    const chips = [..._activeLabelIds].map(id => {
        const name = _activeLabelNames.get(id) || id;
        return `<span class="fn-filter-label-chip">
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:-2px">sell</span>
            ${escapeHtml(name)}
            <button class="fn-filter-chip-clear" onclick="removeLabelFilter(${id})" title="Bỏ nhãn này">
                <span class="material-symbols-outlined" style="font-size:14px">close</span>
            </button>
        </span>`;
    }).join('');

    bar.innerHTML = `
        ${chips}
        <button class="fn-filter-clear-all" onclick="filterNotesByLabel(0)" title="Xóa tất cả bộ lọc">
            <span class="material-symbols-outlined" style="font-size:15px;vertical-align:-2px">filter_list_off</span>
            Xóa tất cả
        </button>`;
}

/** Replace the notes container content with the filtered results */
function _renderFilteredNotes(notes) {
    let container = document.getElementById('notesContainer');

    if (notes.length === 0) {
        if (container) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5 text-muted fn-empty-state">
                        <span class="material-symbols-outlined d-block mb-3">label_off</span>
                        <p class="small opacity-75">Không có ghi chú nào với các nhãn đã chọn.</p>
                    </div>
                </div>`;
        }
        return;
    }

    if (!container) {
        const emptyState = document.querySelector('.fn-empty-state');
        const wrapper = emptyState?.closest('.col-12');
        emptyState?.remove();
        container = document.createElement('div');
        container.className = 'row g-3';
        container.id = 'notesContainer';
        wrapper?.appendChild(container);
    }

    container.innerHTML = notes.map(note => buildNoteCardHtml(note)).join('');
}

// ═══════════════════════════════════════════════════
// LabelPills – Notion-style inline tag pills
// Only active on the full-page note editor (notes/edit)
// ═══════════════════════════════════════════════════

const LabelPills = (() => {
    const COLOR_COUNT = 8;

    // Deterministic color index based on label id
    const _color = (id) => (parseInt(id) || 0) % COLOR_COUNT;

    /* ── State ───────────────────────────────────── */
    let _pillRow = null;   // #fnpLabelPills
    let _chips = null;   // #modalLabelsChips (hidden checkboxes)
    let _canEdit = false;
    let _allLabels = [];     // [{id, name}, …]
    let _picker = null;   // floating dropdown element

    /* ── Init ────────────────────────────────────── */
    function init() {
        _pillRow = document.getElementById('fnpLabelPills');
        _chips = document.getElementById('modalLabelsChips');
        _canEdit = window.__FNP_CAN_EDIT_LABELS === true;
        _allLabels = Array.isArray(window.__FNP_ALL_LABELS) ? window.__FNP_ALL_LABELS : [];

        if (!_pillRow) return; // not on edit page

        render();

        // Keep pills in sync when auto-save or label JS toggles a checkbox
        if (_chips) {
            _chips.addEventListener('change', () => render());
        }

        // Close picker on outside click
        document.addEventListener('click', (e) => {
            if (_picker && !_picker.contains(e.target) && !e.target.closest('.fnp-pill-add')) {
                _closePicker();
            }
        }, true);
    }

    /* ── Render ──────────────────────────────────── */
    function render() {
        if (!_pillRow) return;

        const checkedIds = _getCheckedIds();
        const checkedNames = _getCheckedNames(checkedIds);

        let html = '';

        // Active label pills
        checkedIds.forEach(id => {
            const name = checkedNames[id] || `#${id}`;
            const colorCls = `fnp-pill-c-${_color(id)}`;
            const removeBtn = _canEdit
                ? `<button class="fnp-pill-remove" data-label-id="${id}" title="Bỏ nhãn" type="button">
                       <span class="material-symbols-outlined">close</span>
                   </button>`
                : '';
            html += `<span class="fnp-label-pill ${colorCls}" data-label-id="${id}">
                        ${escapeHtml(name)}${removeBtn}
                     </span>`;
        });

        // + Add button (only for owners)
        if (_canEdit) {
            html += `<button class="fnp-pill-add" id="fnpPillAddBtn" type="button" title="Thêm nhãn">
                        <span class="material-symbols-outlined">add</span>
                        Thêm
                     </button>`;
        }

        _pillRow.innerHTML = html;

        // Wire remove buttons
        _pillRow.querySelectorAll('.fnp-pill-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                _removeLabelPill(parseInt(btn.dataset.labelId));
            });
        });

        // Wire add button
        const addBtn = _pillRow.querySelector('#fnpPillAddBtn');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                _togglePicker(addBtn);
            });
        }
    }

    /* ── Remove a label ──────────────────────────── */
    function _removeLabelPill(labelId) {
        const cb = document.getElementById(`modal_label_${labelId}`);
        if (cb) {
            cb.checked = false;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        }
        render();
        _scheduleAutoSave();
    }

    /* ── Picker dropdown ─────────────────────────── */
    function _togglePicker(anchor) {
        if (_picker) { _closePicker(); return; }

        const checkedIds = new Set(_getCheckedIds());
        const filter = { text: '' };

        _picker = document.createElement('div');
        _picker.className = 'fnp-label-picker';
        _picker.setAttribute('role', 'listbox');
        _picker.innerHTML = _buildPickerHtml(checkedIds, '');

        document.body.appendChild(_picker);
        _positionPicker(anchor);

        // Search input
        const searchInput = _picker.querySelector('.fnp-picker-search');
        searchInput?.focus();
        searchInput?.addEventListener('input', () => {
            filter.text = searchInput.value.trim().toLowerCase();
            const list = _picker.querySelector('.fnp-picker-list');
            if (list) list.innerHTML = _buildPickerItems(_getCheckedIds(), filter.text);
            _wirePickerItems();
        });

        // Enter on search to create new label
        searchInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && searchInput.value.trim()) {
                e.preventDefault();
                _createFromPicker(searchInput.value.trim());
            }
            if (e.key === 'Escape') _closePicker();
        });

        _wirePickerItems();
        _wirePickerNew();
    }

    function _buildPickerHtml(checkedIds, filterText) {
        return `
            <input class="fnp-picker-search" type="text" placeholder="Tìm hoặc tạo nhãn..." value="${escapeAttr(filterText)}">
            <div class="fnp-picker-list">${_buildPickerItems([...checkedIds], filterText)}</div>
            <div class="fnp-picker-divider"></div>
            <div class="fnp-picker-new" id="fnpPickerNew">
                <span class="material-symbols-outlined">add</span>
                Tạo nhãn mới
            </div>`;
    }

    function _buildPickerItems(checkedIdArr, filterText) {
        const checkedSet = new Set(checkedIdArr.map(Number));
        return _allLabels
            .filter(l => !filterText || l.name.toLowerCase().includes(filterText))
            .map(l => {
                const sel = checkedSet.has(Number(l.id)) ? 'selected' : '';
                return `<div class="fnp-picker-item ${sel}" data-label-id="${l.id}" role="option" aria-selected="${!!sel}">
                            <span class="fnp-picker-check"></span>
                            ${escapeHtml(l.name)}
                        </div>`;
            }).join('') || `<div style="padding:0.5rem 0.75rem;font-size:0.8rem;color:var(--fn-on-surface-variant);opacity:.6;">Không có nhãn phù hợp</div>`;
    }

    function _wirePickerItems() {
        _picker?.querySelectorAll('.fnp-picker-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.labelId);
                const cb = document.getElementById(`modal_label_${id}`);
                if (cb) {
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
                item.classList.toggle('selected', cb?.checked);
                item.setAttribute('aria-selected', String(!!cb?.checked));
                render(); // update pills immediately
                _scheduleAutoSave();
            });
        });
    }

    function _wirePickerNew() {
        const newBtn = document.getElementById('fnpPickerNew');
        if (!newBtn) return;
        newBtn.addEventListener('click', () => {
            const search = _picker?.querySelector('.fnp-picker-search');
            const name = search?.value.trim() || '';
            _createFromPicker(name);
        });
    }

    async function _createFromPicker(name) {
        if (!name) return;
        _closePicker();

        try {
            const body = new URLSearchParams({ name });
            const res = await apiFetch(window.FN_LABEL_STORE_URL, 'POST', body);
            const data = await res.json();
            if (!res.ok) { showToast(data.errors?.name?.[0] || data.message || 'Có lỗi', 'error'); return; }

            const label = data.data;
            // Register globally
            _allLabels.push({ id: label.id, name: label.name });
            appendLabelItem(label);   // sidebar sync
            addLabelCheckbox(label);  // checkbox sync

            // Auto-check the new label
            const cb = document.getElementById(`modal_label_${label.id}`);
            if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }

            render();
            _scheduleAutoSave();
        } catch { showToast('Lỗi kết nối', 'error'); }
    }

    function _positionPicker(anchor) {
        if (!_picker || !anchor) return;
        const rect = anchor.getBoundingClientRect();
        const pickerH = 300; // estimated
        const spaceBelow = window.innerHeight - rect.bottom;

        _picker.style.left = Math.min(rect.left, window.innerWidth - 240) + 'px';
        if (spaceBelow < pickerH && rect.top > pickerH) {
            _picker.style.top = (rect.top + window.scrollY - pickerH) + 'px';
        } else {
            _picker.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        }
    }

    function _closePicker() {
        _picker?.remove();
        _picker = null;
    }

    /* ── Helpers ─────────────────────────────────── */
    function _getCheckedIds() {
        if (!_chips) return [];
        return [..._chips.querySelectorAll('input[type="checkbox"]:checked')]
            .map(cb => parseInt(cb.value));
    }

    function _getCheckedNames(ids) {
        const map = {};
        ids.forEach(id => {
            const label = _allLabels.find(l => Number(l.id) === id);
            if (label) map[id] = label.name;
            else {
                // Fallback: read from checkbox span
                const span = _chips?.querySelector(`input#modal_label_${id} ~ .fn-checkbox-text`);
                map[id] = span?.textContent || `#${id}`;
            }
        });
        return map;
    }

    function _scheduleAutoSave() {
        // Trigger auto-save.js by dispatching change on the chips container
        _chips?.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ── Public API ──────────────────────────────── */
    return { init, render };
})();

// Auto-init on DOMContentLoaded when on the edit page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('fnpLabelPills')) {
        LabelPills.init();

        // Patch addLabelCheckbox to also re-render pills when a new label is added
        const _orig = window.addLabelCheckbox || addLabelCheckbox;
        window.addLabelCheckbox = function (label) {
            _orig(label);
            setTimeout(() => LabelPills.render(), 0);
        };
    }
});


