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

            <hr class="fn-ws-divider">

            {{-- Share Section --}}
            <div class="fn-ws-settings-section">
                <h3 class="fn-ws-settings-heading">Chia sẻ</h3>
                <div class="fn-form-group">
                    <div class="fn-ws-share-input-row">
                        <input type="email" class="fn-form-input" id="wsShareEmail" placeholder="Email người dùng..."
                            autocomplete="off" multiple>
                        <select class="fn-form-select" id="wsSharePermission">
                            <option value="read">Chỉ đọc</option>
                            <option value="edit">Chỉnh sửa</option>
                        </select>
                        <button type="button" class="fn-ws-action-btn fn-ws-action-primary" onclick="submitShareWorkspace()">
                            <span class="material-symbols-outlined">person_add</span>
                        </button>
                    </div>
                </div>
                <div id="wsSharesList" class="fn-ws-shares-list">
                    {{-- Dynamically loaded share records --}}
                </div>
            </div>

            <hr class="fn-ws-divider">

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
