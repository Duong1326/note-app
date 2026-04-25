{{--
    Note Share Modal
    Manages sharing a note with other registered users.
    – Add recipients by email (chip input)
    – Set permission: read | edit
    – View/update/revoke existing shares
--}}

<div id="shareNoteModal" class="fn-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle">
    <div class="fn-modal-card fn-share-modal">

        {{-- Header --}}
        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div class="fn-share-icon-wrap d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
                <span class="material-symbols-outlined">share</span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6" id="shareModalTitle">Chia sẻ ghi chú</h3>
                <p class="text-muted small mb-0">Mời người khác cùng xem hoặc chỉnh sửa.</p>
            </div>
            <button class="fn-modal-close" onclick="closeShareModal()" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Body --}}
        <div class="fn-modal-body px-4 pb-4 pt-3">

            {{-- ── Add recipients form ──────────────────────── --}}
            <form id="shareNoteForm" onsubmit="submitShareNote(event)" novalidate>

                {{-- Email chip input --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">
                        Thêm người nhận
                        <span class="text-muted fw-normal">(nhập email, nhấn Enter hoặc dấu phẩy)</span>
                    </label>
                    <div class="fn-email-chip-container" id="emailChipContainer" onclick="focusEmailInput()">
                        {{-- Chips injected here by JS --}}
                        <input
                            type="text"
                            id="shareEmailInput"
                            class="fn-email-chip-input"
                            placeholder="email@example.com"
                            autocomplete="off"
                            inputmode="email"
                        >
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="shareEmailError"></p>
                </div>

                {{-- Permission selector --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="sharePermission">Quyền truy cập</label>
                    <div class="d-flex gap-3">
                        <label class="d-flex align-items-center gap-2 cursor-pointer" style="cursor:pointer">
                            <input type="radio" name="sharePermission" id="permRead" value="read" checked
                                class="form-check-input mt-0" style="flex-shrink:0">
                            <span>
                                <span class="d-block small fw-semibold">Chỉ đọc</span>
                                <span class="d-block" style="font-size:0.7rem; color:var(--fn-on-surface-variant)">Xem nội dung, không sửa</span>
                            </span>
                        </label>
                        <label class="d-flex align-items-center gap-2 cursor-pointer" style="cursor:pointer">
                            <input type="radio" name="sharePermission" id="permEdit" value="edit"
                                class="form-check-input mt-0" style="flex-shrink:0">
                            <span>
                                <span class="d-block small fw-semibold">Chỉnh sửa</span>
                                <span class="d-block" style="font-size:0.7rem; color:var(--fn-on-surface-variant)">Xem và chỉnh sửa nội dung</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-share-primary d-inline-flex align-items-center gap-1 px-4" id="shareSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">person_add</span>
                        Chia sẻ
                    </button>
                </div>
            </form>

            {{-- ── Existing recipients ──────────────────────── --}}
            <div id="shareRecipientsSection" class="mt-4 d-none">
                <hr class="my-3">
                <p class="small fw-semibold text-secondary mb-2">
                    <span class="material-symbols-outlined fn-icon-sm align-middle">group</span>
                    Đã chia sẻ với
                </p>
                <div class="fn-share-recipient-list" id="shareRecipientList">
                    {{-- Populated by JS --}}
                </div>
            </div>

        </div>
    </div>
</div>
