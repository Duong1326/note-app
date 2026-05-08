{{--
Note Lock Modals – Refactored with Bootstrap 5 utilities
1. #unlockNoteModal – Enter password to view/edit a locked note
2. #enableLockModal – Set a new lock password
3. #changeLockModal – Change existing lock password
4. #disableLockModal – Remove password protection
--}}

{{-- ── Macro: password field ──────────────────────────────────── --}}
{{-- Blade doesn't support macros inline, so we repeat the pattern --}}

{{-- ══════════════════════════════════════════════
1. UNLOCK MODAL
══════════════════════════════════════════════ --}}
<div id="unlockNoteModal" class="fn-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="unlockModalTitle">
    <div class="fn-modal-card fn-lock-modal">

        {{-- Header --}}
        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div class="fn-lock-icon-wrap d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
                <span class="material-symbols-outlined">lock</span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6" id="unlockModalTitle">Ghi chú đã bị khoá</h3>
                <p class="text-muted small mb-0">Nhập mật khẩu để tiếp tục.</p>
            </div>
            <button class="fn-modal-close" onclick="closeLockModal('unlockNoteModal')" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Body --}}
        <div class="fn-modal-body px-4 pb-4 pt-3">
            <form id="unlockNoteForm" onsubmit="submitUnlock(event)" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="unlockPassword">Mật khẩu ghi
                        chú</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="unlockPassword" class="form-control" placeholder="Nhập mật khẩu…"
                            autocomplete="current-password" required>
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('unlockPassword', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="unlockPasswordError"></p>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-light" onclick="closeLockModal('unlockNoteModal')">Huỷ</button>
                    <button type="submit" class="btn btn-lock-primary d-inline-flex align-items-center gap-1"
                        id="unlockSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">lock_open</span>
                        Mở khoá
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════
2. ENABLE LOCK MODAL
══════════════════════════════════════════════ --}}
<div id="enableLockModal" class="fn-modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="enableLockModalTitle">
    <div class="fn-modal-card fn-lock-modal">

        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div class="fn-lock-icon-wrap d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
                <span class="material-symbols-outlined">lock</span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6" id="enableLockModalTitle">Khoá ghi chú</h3>
                <p class="text-muted small mb-0">Đặt mật khẩu riêng cho ghi chú này.</p>
            </div>
            <button class="fn-modal-close" onclick="closeLockModal('enableLockModal')" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="fn-modal-body px-4 pb-4 pt-3">
            <form id="enableLockForm" onsubmit="submitEnableLock(event)" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="enableLockPassword">Mật khẩu
                        mới</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="enableLockPassword" class="form-control"
                            placeholder="Ít nhất 6 ký tự" autocomplete="new-password" required minlength="6">
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('enableLockPassword', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="enableLockPasswordError"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="enableLockConfirm">Xác nhận mật
                        khẩu</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="enableLockConfirm" class="form-control"
                            placeholder="Nhập lại mật khẩu" autocomplete="new-password" required>
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('enableLockConfirm', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="enableLockConfirmError"></p>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-light" onclick="closeLockModal('enableLockModal')">Huỷ</button>
                    <button type="submit" class="btn btn-lock-primary d-inline-flex align-items-center gap-1"
                        id="enableLockSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">lock</span>
                        Khoá ghi chú
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════
3. CHANGE PASSWORD MODAL
══════════════════════════════════════════════ --}}
<div id="changeLockModal" class="fn-modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="changeLockModalTitle">
    <div class="fn-modal-card fn-lock-modal">

        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div
                class="fn-lock-icon-wrap fn-lock-change d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
                <span class="material-symbols-outlined">key</span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6" id="changeLockModalTitle">Đổi mật khẩu khoá</h3>
                <p class="text-muted small mb-0">Nhập mật khẩu hiện tại và mật khẩu mới.</p>
            </div>
            <button class="fn-modal-close" onclick="closeLockModal('changeLockModal')" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="fn-modal-body px-4 pb-4 pt-3">
            <form id="changeLockForm" onsubmit="submitChangeLockPassword(event)" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="changeLockCurrent">Mật khẩu hiện
                        tại</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="changeLockCurrent" class="form-control"
                            placeholder="Nhập mật khẩu hiện tại" autocomplete="current-password" required>
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('changeLockCurrent', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="changeLockCurrentError"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="changeLockNew">Mật khẩu mới</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="changeLockNew" class="form-control" placeholder="Ít nhất 6 ký tự"
                            autocomplete="new-password" required minlength="6">
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('changeLockNew', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="changeLockNewError"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="changeLockConfirm">Xác nhận mật khẩu
                        mới</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="changeLockConfirm" class="form-control"
                            placeholder="Nhập lại mật khẩu mới" autocomplete="new-password" required>
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('changeLockConfirm', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="changeLockConfirmError"></p>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-light" onclick="closeLockModal('changeLockModal')">Huỷ</button>
                    <button type="submit" class="btn btn-lock-primary d-inline-flex align-items-center gap-1"
                        id="changeLockSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">key</span>
                        Đổi mật khẩu
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════
4. DISABLE LOCK MODAL
══════════════════════════════════════════════ --}}
<div id="disableLockModal" class="fn-modal-overlay" role="dialog" aria-modal="true"
    aria-labelledby="disableLockModalTitle">
    <div class="fn-modal-card fn-lock-modal">

        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div
                class="fn-lock-icon-wrap fn-lock-danger d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
                <span class="material-symbols-outlined">no_encryption</span>
            </div>
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6" id="disableLockModalTitle">Gỡ khoá ghi chú</h3>
                <p class="text-muted small mb-0">Xác nhận mật khẩu để tắt bảo vệ.</p>
            </div>
            <button class="fn-modal-close" onclick="closeLockModal('disableLockModal')" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="fn-modal-body px-4 pb-4 pt-3">
            <form id="disableLockForm" onsubmit="submitDisableLock(event)" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary" for="disableLockPassword">Mật khẩu hiện
                        tại</label>
                    <div class="fn-pw-wrap">
                        <input type="password" id="disableLockPassword" class="form-control"
                            placeholder="Nhập mật khẩu để xác nhận" autocomplete="current-password" required>
                        <button type="button" class="fn-pw-eye"
                            onclick="togglePasswordVisibility('disableLockPassword', this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="disableLockPasswordError"></p>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-light"
                        onclick="closeLockModal('disableLockModal')">Huỷ</button>
                    <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-1"
                        id="disableLockSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">no_encryption</span>
                        Gỡ khoá
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>