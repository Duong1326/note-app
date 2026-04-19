/**
 * labels.js – Sidebar Label Management AJAX logic.
 */

function toggleAddLabelForm() {
    const btn = document.getElementById('sidebarAddBtn');
    const form = document.getElementById('sidebarLabelAddForm');
    const input = document.getElementById('newSidebarLabelInput');

    if (form.classList.contains('d-none')) {
        form.classList.remove('d-none');
        btn.classList.add('d-none');
        input.focus();
    } else {
        form.classList.add('d-none');
        btn.classList.remove('d-none');
        input.value = '';
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
        appendLabelItem(label);

        toggleAddLabelForm(); // Hide form on success
        addLabelCheckbox(label); // Also update the note modal checkboxes

    } catch {
        showToast('Lỗi kết nối', 'error');
    }
}

async function deleteLabel(labelId) {
    if (!confirm('Bạn có chắc chắn muốn xóa nhãn này?')) return;

    const row = document.querySelector(`.fn-sidebar-label-item[data-label-id="${labelId}"]`);

    try {
        const res = await apiFetch(`/labels/${labelId}`, 'DELETE');
        if (!res.ok) throw new Error('Xóa nhãn thất bại');

        if (row) {
            row.style.transition = 'opacity 0.3s ease, margin-top 0.3s ease';
            row.style.opacity = '0';
            row.style.marginTop = `-${row.offsetHeight}px`;
            setTimeout(() => row.remove(), 320);
        }

        // Remove checkbox from note modal
        document.getElementById(`modal_label_${labelId}`)?.closest('.fn-checkbox-label')?.remove();

        // Remove badges from visible note cards
        document.querySelectorAll(`.fn-label-badge[data-label-id="${labelId}"]`).forEach(badge => {
            badge.style.transition = 'opacity 0.2s';
            badge.style.opacity = '0';
            setTimeout(() => {
                const parent = badge.parentNode;
                badge.remove();
                if (parent && parent.children.length === 0) {
                    parent.remove(); // removes .fn-note-labels if empty
                }
            }, 200);
        });

        // Update data-labels attribute inside edit buttons so the modal won't try checking deleted labels
        document.querySelectorAll('.dropdown-item[data-labels]').forEach(btn => {
            try {
                let labelsArr = JSON.parse(btn.getAttribute('data-labels') || '[]');
                labelsArr = labelsArr.filter(id => id != labelId);
                btn.setAttribute('data-labels', JSON.stringify(labelsArr));
            } catch (e) { }
        });
    } catch (err) {
        if (row) {
            row.style.opacity = '';
            row.style.marginTop = '';
        }
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

        // Update display
        const nameEl = row.querySelector('.fn-sidebar-label-name');
        nameEl.textContent = data.data.name;

        cancelRenameLabel(labelId);

        // Update checkbox label in note modal
        const cbLabel = document.querySelector(`label[for="modal_label_${labelId}"] .fn-checkbox-text`);
        if (cbLabel) cbLabel.textContent = data.data.name;

        // Update badges on visible note cards using attribute selector for bulletproof accuracy
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
                    <button onclick="startRenameLabel(${label.id})" title="Đổi tên"><span class="material-symbols-outlined">edit</span></button>
                    <button onclick="deleteLabel(${label.id})" title="Xóa"><span class="material-symbols-outlined">delete</span></button>
                </div>
            </div>
            <div class="fn-sidebar-label-edit d-none">
                <input type="text" class="fn-sidebar-label-input" value="${escapeAttr(label.name)}"
                    onkeydown="if(event.key==='Enter')saveRenameLabel(${label.id});if(event.key==='Escape')cancelRenameLabel(${label.id});"
                    onblur="cancelRenameLabel(${label.id})">
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

    // Reset input value to current name
    const name = row.querySelector('.fn-sidebar-label-name').textContent;
    row.querySelector('.fn-sidebar-label-input').value = name;
}

function addLabelCheckbox(label) {
    const chipsContainer = document.getElementById('modalLabelsChips');
    if (!chipsContainer) return;

    if (document.getElementById(`modal_label_${label.id}`)) return;

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

    if (forceClose || input.classList.contains('d-none') === false) {
        input.classList.add('d-none');
        btn.classList.remove('d-none');
        input.value = '';
    } else {
        input.classList.remove('d-none');
        btn.classList.add('d-none');
        input.focus();
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
        appendLabelItem(label); // update sidebar
        addLabelCheckbox(label); // update modal

        // Auto-check the newly created label
        const checkbox = document.getElementById(`modal_label_${label.id}`);
        if (checkbox) checkbox.checked = true;

        toggleModalAddLabelForm(true);

    } catch {
        showToast('Lỗi kết nối', 'error');
    }
}
