{{-- ═══ Create Workspace Modal ═══ --}}
<div id="createWorkspaceModal" class="fn-modal-overlay" onclick="if(event.target===this) closeCreateWorkspaceModal()">
    <div class="fn-modal-card fn-modal-sm">
        <div class="fn-modal-header">
            <div class="d-flex align-items-center gap-2">
                <div class="fn-modal-icon"><span class="material-symbols-outlined">create_new_folder</span></div>
                <h2 class="fn-modal-title">Tạo Workspace mới</h2>
            </div>
            <button type="button" class="fn-modal-close" onclick="closeCreateWorkspaceModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="fn-modal-body">
            <div class="fn-form-group">
                <label class="fn-form-label" for="wsCreateName">Tên workspace</label>
                <input type="text" class="fn-form-input" id="wsCreateName" placeholder="Ví dụ: Dự án tốt nghiệp..."
                    maxlength="255" autocomplete="off">
            </div>
            <div class="fn-form-group">
                <label class="fn-form-label" for="wsCreateDesc">Mô tả <span class="text-muted">(tùy chọn)</span></label>
                <textarea class="fn-form-input" id="wsCreateDesc" rows="2" placeholder="Mô tả ngắn gọn..."
                    maxlength="1000"></textarea>
            </div>
        </div>
        <div class="fn-modal-footer">
            <button type="button" class="fn-modal-btn-cancel" onclick="closeCreateWorkspaceModal()">Hủy</button>
            <button type="button" class="fn-modal-btn-save" onclick="submitCreateWorkspace()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">add</span>
                Tạo
            </button>
        </div>
    </div>
</div>

{{-- ═══ Workspace Settings Modal ═══ --}}
<div id="wsSettingsModal" class="fn-modal-overlay" onclick="if(event.target===this) closeWorkspaceSettings()">
    <div class="fn-modal-card fn-modal-sm">
        <div class="fn-modal-header">
            <div class="d-flex align-items-center gap-2">
                <div class="fn-modal-icon"><span class="material-symbols-outlined">settings</span></div>
                <h2 class="fn-modal-title">Cài đặt Workspace</h2>
            </div>
            <button type="button" class="fn-modal-close" onclick="closeWorkspaceSettings()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="fn-modal-body">
            {{-- Rename Section --}}
            <div class="fn-ws-settings-section">
                <h3 class="fn-ws-settings-heading">Đổi tên</h3>
                <div class="fn-form-group">
                    <input type="text" class="fn-form-input" id="wsSettingsName" placeholder="Tên workspace" maxlength="255">
                </div>
                <div class="fn-form-group">
                    <textarea class="fn-form-input" id="wsSettingsDesc" rows="2" placeholder="Mô tả (tùy chọn)"
                        maxlength="1000"></textarea>
                </div>
                <button type="button" class="fn-ws-action-btn fn-ws-action-primary" onclick="submitRenameWorkspace()">
                    <span class="material-symbols-outlined">save</span> Lưu thay đổi
                </button>
            </div>

            <hr class="fn-ws-divider">

            {{-- Lock Section --}}
            <div class="fn-ws-settings-section">
                <h3 class="fn-ws-settings-heading">Bảo mật</h3>
                <div id="wsLockSection">
                    {{-- Dynamic content: enable lock or manage lock --}}
                </div>
            </div>



            {{-- Delete Section --}}
            <div class="fn-ws-settings-section" id="wsDeleteSection">
                <h3 class="fn-ws-settings-heading text-danger">Vùng nguy hiểm</h3>
                <p class="fn-ws-danger-text">Xóa workspace sẽ xóa <strong>tất cả ghi chú</strong> bên trong. Hành động này không thể hoàn tác.</p>
                <button type="button" class="fn-ws-action-btn fn-ws-action-danger" onclick="confirmDeleteWorkspace()">
                    <span class="material-symbols-outlined">delete_forever</span> Xóa workspace
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ═══ Workspace Lock Password Modal ═══ --}}
<div id="wsLockModal" class="fn-modal-overlay" onclick="if(event.target===this) closeWsLockModal()">
    <div class="fn-modal-card fn-modal-sm">
        <div class="fn-modal-header">
            <div class="d-flex align-items-center gap-2">
                <div class="fn-modal-icon"><span class="material-symbols-outlined">lock</span></div>
                <h2 class="fn-modal-title" id="wsLockModalTitle">Khoá workspace</h2>
            </div>
            <button type="button" class="fn-modal-close" onclick="closeWsLockModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="fn-modal-body" id="wsLockModalBody">
            {{-- Dynamic content based on lock action --}}
        </div>
        <div class="fn-modal-footer">
            <button type="button" class="fn-modal-btn-cancel" onclick="closeWsLockModal()">Hủy</button>
            <button type="button" class="fn-modal-btn-save" id="wsLockSubmitBtn" onclick="submitWsLockAction()">
                Xác nhận
            </button>
        </div>
    </div>
</div>

{{-- ═══ Workspace Verify Password Modal (for accessing locked workspace) ═══ --}}
<div id="wsVerifyModal" class="fn-modal-overlay" onclick="if(event.target===this) closeWsVerifyModal()">
    <div class="fn-modal-card fn-modal-sm">
        <div class="fn-modal-header">
            <div class="d-flex align-items-center gap-2">
                <div class="fn-modal-icon"><span class="material-symbols-outlined">lock_open</span></div>
                <h2 class="fn-modal-title">Nhập mật khẩu workspace</h2>
            </div>
            <button type="button" class="fn-modal-close" onclick="closeWsVerifyModal()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="fn-modal-body">
            <p class="fn-ws-verify-text">Workspace <strong id="wsVerifyName"></strong> đã được khoá. Vui lòng nhập mật khẩu để truy cập.</p>
            <div class="fn-form-group">
                <input type="password" class="fn-form-input" id="wsVerifyPassword" placeholder="Mật khẩu..."
                    onkeydown="if(event.key==='Enter') submitWsVerify()">
            </div>
            <div class="fn-ws-error d-none" id="wsVerifyError"></div>
        </div>
        <div class="fn-modal-footer">
            <button type="button" class="fn-modal-btn-cancel" onclick="closeWsVerifyModal()">Hủy</button>
            <button type="button" class="fn-modal-btn-save" onclick="submitWsVerify()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">lock_open</span>
                Mở khoá
            </button>
        </div>
    </div>
</div>

{{-- ═══ Workspace Share Popover – mirrors note-share-modal design ═══ --}}
<div id="wsSharePopover" class="fn-modal-overlay share-popover-mode" style="display:none;" onclick="if(event.target===this) closeWsShareModal()">
    <div class="fn-modal-card fn-share-modal" id="wsSharePopoverCard">

        {{-- Header --}}
        <div class="fn-modal-header d-flex align-items-start gap-3 p-4 pb-2">
            <div class="flex-grow-1">
                <h3 class="fw-bold mb-0 fs-6">Chia sẻ Workspace</h3>
                <p class="text-secondary small mb-0 mt-1" id="wsShareModalSubtitle" style="font-size:0.78rem;"></p>
            </div>
            <button class="fn-modal-close" onclick="closeWsShareModal()" aria-label="Đóng">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Body --}}
        <div class="fn-modal-body px-4 pb-4 pt-3">

            {{-- Add recipients form --}}
            <form id="wsShareForm" onsubmit="submitWsShareFromModal(event)" novalidate>

                {{-- Email chip input --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">
                        Thêm người nhận
                    </label>
                    <div class="fn-email-chip-container" id="wsEmailChipContainer" onclick="wssFocusEmailInput()">
                        {{-- Chips injected by JS --}}
                        <input type="text" id="wsShareModalEmail" class="fn-email-chip-input"
                            placeholder="email@example.com" autocomplete="off" inputmode="email">
                    </div>
                    <p class="text-danger small mt-1 mb-0 d-none" id="wsShareEmailError"></p>
                </div>

                {{-- Permission radio --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Quyền truy cập</label>
                    <div class="d-flex gap-3">
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                            <input type="radio" name="wsSharePermission" id="wsPermRead" value="read" checked
                                class="form-check-input mt-0" style="flex-shrink:0">
                            <span class="d-block small fw-semibold">Chỉ đọc</span>
                        </label>
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                            <input type="radio" name="wsSharePermission" id="wsPermEdit" value="edit"
                                class="form-check-input mt-0" style="flex-shrink:0">
                            <span class="d-block small fw-semibold">Chỉnh sửa</span>
                        </label>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-share-primary d-inline-flex align-items-center gap-1 px-4"
                        id="wsShareSubmitBtn">
                        <span class="material-symbols-outlined fn-icon-sm">person_add</span>
                        Chia sẻ
                    </button>
                </div>
            </form>

            {{-- Existing recipients --}}
            <div id="wsShareRecipientsSection" class="mt-4 d-none">
                <hr class="my-3">
                <p class="small fw-semibold text-secondary mb-2">
                    <span class="material-symbols-outlined fn-icon-sm align-middle">group</span>
                    Đã chia sẻ với
                </p>
                <div class="fn-share-recipient-list" id="wsShareModalList">
                    {{-- Populated by JS --}}
                </div>
            </div>

        </div>
    </div>
</div>
