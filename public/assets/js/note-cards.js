/**
 * note-cards.js – Note card DOM rendering, patching, and animation helpers.
 * Depends on: app.js (escapeHtml, escapeAttr)
 */

/**
 * Extract clean text excerpt from HTML content.
 * Strips image blocks, dividers, and other non-text elements before removing tags.
 */
function contentToExcerpt(html, maxLen = 120) {
    if (!html) return '';
    // Remove image blocks, dividers (they contain button text like "close")
    let clean = html
        .replace(/<div[^>]*class="fn-content-image-block"[^>]*>[\s\S]*?<\/div>/gi, '')
        .replace(/<div[^>]*class="fn-content-divider"[^>]*>[\s\S]*?<\/div>/gi, '')
        .replace(/<[^>]*>/g, '')   // strip remaining tags
        .replace(/\s+/g, ' ')      // collapse whitespace
        .trim();
    return clean.substring(0, maxLen);
}

// ═══════════════════════════════════════════════════
// Card Removal Animation
// ═══════════════════════════════════════════════════

function removeNoteCard(col) {
    col.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    col.style.opacity = '0';
    col.style.transform = 'scale(0.85)';
    col.style.pointerEvents = 'none';
    setTimeout(() => col.remove(), 380);
}

// ═══════════════════════════════════════════════════
// Card Patching (in-place updates)
// ═══════════════════════════════════════════════════

function updateCardLabels(col, labels) {
    let el = col.querySelector('.fn-note-labels');
    if (!labels || labels.length === 0) { el?.remove(); return; }

    const html = labels.slice(0, 3)
        .map(l => `<span class="fn-label-badge" data-label-id="${l.id}">${escapeHtml(l.name)}</span>`)
        .join('');

    if (el) {
        el.innerHTML = html;
    } else {
        const div = document.createElement('div');
        div.className = 'fn-note-labels';
        div.innerHTML = html;
        col.querySelector('.fn-note-card-header').insertAdjacentElement('afterend', div);
    }
}

function updateCardThumbnail(col, attachments) {
    let thumb = col.querySelector('.fn-note-thumb');
    const card = col.querySelector('.fn-note-card');

    if (attachments && attachments.length > 0) {
        const url = attachments[0].thumbnail_url || attachments[0].url;
        if (thumb) {
            thumb.src = url;
        } else {
            const img = document.createElement('img');
            img.className = 'fn-note-thumb';
            img.src = url;
            img.alt = 'Note image';
            card.insertAdjacentElement('afterbegin', img);
        }
    } else if (thumb) {
        thumb.remove();
    }
}

function patchNoteCard(noteId, note) {
    const col = document.querySelector(`.note-col[data-note-id="${noteId}"]`);
    if (!col) return;

    col.querySelector('.fn-note-title').textContent = note.title;
    col.querySelector('.fn-note-excerpt').textContent = contentToExcerpt(note.content);
    col.querySelector('.fn-note-date').textContent = note.updated_at ?? 'Vừa xong';

    updateCardLabels(col, note.labels);
    updateCardThumbnail(col, note.attachments);

    const editBtn = col.querySelector('.dropdown-menu li:first-child a');
    if (editBtn) {
        editBtn.dataset.title = note.title;
        editBtn.dataset.content = note.content ?? '';
        editBtn.dataset.labels = JSON.stringify(note.labels?.map(l => l.id) ?? []);
        editBtn.dataset.attachments = JSON.stringify(note.attachments ?? []);
    }
}

function patchPinCard(col, noteId, isPinned) {
    const pinLink = col.querySelector('.dropdown-menu li:nth-child(2) a');
    if (pinLink) {
        const icon = pinLink.querySelector('.material-symbols-outlined');
        icon.style.fontVariationSettings = isPinned ? "'FILL' 1" : '';

        // Xóa tất cả text node cũ (tránh hiện "Bỏ ghim Ghim" đồng thời)
        [...pinLink.childNodes]
            .filter(n => n.nodeType === Node.TEXT_NODE)
            .forEach(n => n.remove());
        pinLink.appendChild(document.createTextNode(isPinned ? ' Bỏ ghim' : ' Ghim'));

        pinLink.setAttribute('onclick', `togglePinAjax(${noteId}, ${isPinned})`);
    }
    const meta = col.querySelector('.fn-note-meta');
    const starEl = meta.querySelector('.fn-pin-star');
    if (isPinned && !starEl) {
        const star = document.createElement('span');
        star.className = 'material-symbols-outlined fn-pin-star';
        star.style.fontVariationSettings = "'FILL' 1";
        star.textContent = 'star';
        meta.appendChild(star);
    } else if (!isPinned && starEl) {
        starEl.remove();
    }
}

// ═══════════════════════════════════════════════════
// Card HTML Builder
// ═══════════════════════════════════════════════════

function buildNoteCardHtml(note) {
    const labelIds = JSON.stringify(note.labels?.map(l => l.id) ?? []);
    const attachmentsJson = JSON.stringify(note.attachments ?? []);
    const labelsHtml = (note.labels?.length > 0)
        ? `<div class="fn-note-labels">${note.labels.slice(0, 3).map(l =>
            `<span class="fn-label-badge" data-label-id="${l.id}">${escapeHtml(l.name)}</span>`).join('')}</div>`
        : '';
    const thumbHtml = (note.attachments?.length > 0)
        ? `<img class="fn-note-thumb" src="${escapeAttr(note.attachments[0].thumbnail_url || note.attachments[0].url)}" alt="Note image">`
        : '';
    const pinFill = note.is_pinned ? ` style="font-variation-settings:'FILL' 1;"` : '';
    const pinText = note.is_pinned ? 'Bỏ ghim' : 'Ghim';
    const pinStar = note.is_pinned
        ? `<span class="material-symbols-outlined fn-pin-star" style="font-variation-settings:'FILL' 1;">star</span>`
        : '';
    const lockBadge = note.is_locked
        ? `<span class="material-symbols-outlined fn-lock-badge" title="Ghi chú đã khoá">lock</span>`
        : '';
    const lockMenuItems = note.is_locked
        ? `<li class="fn-lock-menu-item">
                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                   href="javascript:void(0)" onclick="openChangeLockModal(${note.id})">
                    <span class="material-symbols-outlined fn-icon-sm">key</span> Đổi mật khẩu khoá
                </a>
           </li>
           <li class="fn-lock-menu-item">
                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-warning"
                   href="javascript:void(0)" onclick="openDisableLockModal(${note.id})">
                    <span class="material-symbols-outlined fn-icon-sm">no_encryption</span> Gỡ khoá
                </a>
           </li>`
        : `<li class="fn-lock-menu-item">
                <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                   href="javascript:void(0)" onclick="openEnableLockModal(${note.id})">
                    <span class="material-symbols-outlined fn-icon-sm">lock</span> Khoá bằng mật khẩu
                </a>
           </li>`;
    const excerpt = contentToExcerpt(note.content);

    return `
        <div class="col-12 col-md-6 col-lg-4 col-xl-3 fn-note-adding note-col" data-note-id="${note.id}"${note.is_pinned ? ' data-pinned="1"' : ''}${note.is_locked ? ' data-locked="1"' : ''}>
            <div class="fn-note-card">
                ${thumbHtml}
                <div class="dropdown fn-note-dropdown">
                    <span class="material-symbols-outlined fn-note-more"
                        data-bs-toggle="dropdown" aria-expanded="false">more_vert</span>
                    <ul class="dropdown-menu dropdown-menu-end fn-dropdown-menu shadow-sm border-0 rounded-3">
                        <li>
                             <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               data-id="${note.id}"
                               data-title="${escapeAttr(note.title)}"
                               data-content="${escapeAttr(note.content ?? '')}"
                               data-labels='${labelIds}'
                               data-attachments='${escapeAttr(attachmentsJson)}'
                               onclick="requireUnlock(${note.id}, (tok) => openEditNoteModal(this, tok))">
                                <span class="material-symbols-outlined fn-icon-sm">edit</span>
                                Chỉnh sửa
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               onclick="togglePinAjax(${note.id}, ${!!note.is_pinned})">
                                <span class="material-symbols-outlined fn-icon-sm"${pinFill}>push_pin</span>
                                ${pinText}
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger"
                               href="javascript:void(0)"
                               onclick="deleteNoteAjax(${note.id})">
                                <span class="material-symbols-outlined fn-icon-sm">delete</span>
                                Xóa
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        ${lockMenuItems}
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="javascript:void(0)"
                               onclick="openShareModal(${note.id})">
                                <span class="material-symbols-outlined fn-icon-sm" style="color:#0f9b58">share</span>
                                Chia sẻ
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="fn-note-card-header">
                    <h4 class="fn-note-title">${escapeHtml(note.title)}</h4>
                </div>
                ${labelsHtml}
                <p class="fn-note-excerpt">${escapeHtml(excerpt)}</p>
                <div class="fn-note-meta">
                    <span class="fn-note-date">${note.updated_at ?? 'Vừa xong'}</span>
                    <div class="d-flex align-items-center gap-1">
                        ${pinStar}
                        ${lockBadge}
                    </div>
                </div>
            </div>
        </div>`;
}

function prependNoteCard(note) {
    let container = document.getElementById('notesContainer');
    if (!container) {
        // Remove empty state and create container
        const emptyState = document.querySelector('.fn-empty-state');
        const wrapper = emptyState?.closest('.col-12');
        emptyState?.remove();
        container = document.createElement('div');
        container.className = 'row g-3';
        container.id = 'notesContainer';
        wrapper?.appendChild(container);
    }
    container.insertAdjacentHTML('afterbegin', buildNoteCardHtml(note));
}

// ═══════════════════════════════════════════════════
// Card Movement Animations
// ═══════════════════════════════════════════════════

/**
 * Shared fade-out → reposition → fade-in animation for card movement.
 * @param {Element} col - The note-col element to animate.
 * @param {function} repositionFn - Called while col is hidden to reposition it.
 */
function _animateCardMove(col, repositionFn) {
    col.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
    col.style.opacity = '0';
    col.style.transform = 'scale(0.95)';
    setTimeout(() => {
        repositionFn();
        requestAnimationFrame(() => {
            col.style.opacity = '1';
            col.style.transform = 'scale(1)';
        });
    }, 260);
}

function moveCardAfterPin(col, isPinned) {
    const container = document.getElementById('notesContainer');
    if (!container) return;

    // Update data attribute FIRST so pinned sibling queries are accurate
    if (isPinned) col.dataset.pinned = '1';
    else delete col.dataset.pinned;

    _animateCardMove(col, () => {
        if (isPinned) {
            container.prepend(col);
        } else {
            const pinnedCols = [...container.querySelectorAll('.note-col[data-pinned="1"]')]
                .filter(c => c !== col);
            if (pinnedCols.length > 0) {
                pinnedCols[pinnedCols.length - 1].insertAdjacentElement('afterend', col);
            } else {
                container.prepend(col);
            }
        }
    });
}

function moveCardToTopOfUnpinned(col) {
    const container = document.getElementById('notesContainer');
    if (!container || !col || col.dataset.pinned === '1') return;

    _animateCardMove(col, () => {
        const pinnedCols = [...container.querySelectorAll('.note-col[data-pinned="1"]')]
            .filter(c => c !== col);
        if (pinnedCols.length > 0) {
            pinnedCols[pinnedCols.length - 1].insertAdjacentElement('afterend', col);
        } else {
            container.prepend(col);
        }
    });
}
