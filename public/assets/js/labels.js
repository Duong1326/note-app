/**
 * labels.js – Sidebar and modal label management via AJAX.
 */

// ═══════════════════════════════════════════════════
// Sidebar Label Form
// ═══════════════════════════════════════════════════

function toggleAddLabelForm(forceClose = false) {
    const btn   = document.getElementById('sidebarAddBtn');
    const form  = document.getElementById('sidebarLabelAddForm');
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
    const name  = input?.value.trim();
    if (name) {
        await createLabel();
    } else {
        toggleAddLabelForm(true);
    }
}

async function createLabel() {
    const input = document.getElementById('newSidebarLabelInput');
    const name  = input.value.trim();
    if (!name) return toggleAddLabelForm();

    try {
        const body = new URLSearchParams({ name });
        const res  = await apiFetch(window.FN_LABEL_STORE_URL, 'POST', body);
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
            row.style.opacity    = '0';
            row.style.marginTop  = `-${row.offsetHeight}px`;
            setTimeout(() => row.remove(), 320);
        }

        // Remove checkbox from note modal
        document.getElementById(`modal_label_${labelId}`)
            ?.closest('.fn-checkbox-label')?.remove();

        // Fade-remove badges from visible note cards
        document.querySelectorAll(`.fn-label-badge[data-label-id="${labelId}"]`).forEach(badge => {
            badge.style.transition = 'opacity 0.2s';
            badge.style.opacity    = '0';
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
    const row     = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);
    const input   = row?.querySelector('.fn-sidebar-label-input');
    const newName = input?.value.trim();

    if (!newName) return cancelRenameLabel(labelId);

    try {
        const body = new URLSearchParams({ name: newName, _method: 'PUT' });
        const res  = await apiFetch(`/labels/${labelId}`, 'POST', body);
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
                <div class="fn-sidebar-label-info">
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
    const btn   = document.getElementById('modalAddLabelBtn');
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
    const name  = input?.value.trim();
    if (name) {
        await createLabelFromModal();
    } else {
        toggleModalAddLabelForm(true);
    }
}

async function createLabelFromModal() {
    const input = document.getElementById('modalNewLabelInput');
    const name  = input.value.trim();
    if (!name) {
        toggleModalAddLabelForm(true);
        return;
    }

    try {
        const body = new URLSearchParams({ name });
        const res  = await apiFetch(window.FN_LABEL_STORE_URL, 'POST', body);
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
