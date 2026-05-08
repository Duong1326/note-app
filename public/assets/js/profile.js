// ═════════════════════════════════════════════════
// Client-side image compression (Canvas API)
// Scale ảnh về tối đa MAX_PX × MAX_PX và nén JPEG.
// Giảm file vài MB → ~100-200 KB, tăng tốc upload đáng kể.
// ═════════════════════════════════════════════════
const MAX_PX   = 800;   // px – chiều dài cạnh lớn nhất
const QUALITY  = 0.82;  // 0–1 JPEG quality

/**
 * @param {File} file - File ảnh gốc
 * @returns {Promise<Blob>} Blob ảnh đã nén
 */
function compressImage(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            let { width, height } = img;

            // Scale down nếu ảnh lớn hơn MAX_PX
            if (width > MAX_PX || height > MAX_PX) {
                if (width >= height) {
                    height = Math.round((height / width) * MAX_PX);
                    width  = MAX_PX;
                } else {
                    width  = Math.round((width / height) * MAX_PX);
                    height = MAX_PX;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width  = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (blob) resolve(blob);
                    else reject(new Error('Canvas compression failed'));
                },
                'image/jpeg',
                QUALITY
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Image load failed'));
        };

        img.src = objectUrl;
    });
}

document.addEventListener('DOMContentLoaded', () => {

    // ═════════════════════════════════════════════════
    // 1. Avatar: nén ảnh trên browser → upload lên server
    //    (runs in background, user can keep editing name/bio)
    // ═════════════════════════════════════════════════
    const fileInput = document.getElementById('avatarFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) return;

            if (file.size > 10 * 1024 * 1024) {
                showToast('Kích thước ảnh không được vượt quá 10MB.', 'error');
                fileInput.value = '';
                return;
            }

            // Show local preview instantly (both profile + header)
            const previewUrl = URL.createObjectURL(file);
            setAvatarImage(previewUrl);
            setHeaderAvatar(previewUrl);

            const uploadUrl = fileInput.dataset.uploadUrl;
            const wrapper = document.querySelector('.fn-avatar-wrapper');

            // Show loading state on avatar
            let spinner = null;
            if (wrapper) {
                wrapper.style.position = 'relative';
                spinner = document.createElement('div');
                spinner.className = 'fn-avatar-spinner';
                spinner.innerHTML = '<span class="spinner-border text-primary" style="width: 2rem; height: 2rem;" role="status"></span>';
                spinner.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.6);border-radius:50%;';
                wrapper.appendChild(spinner);
            }

            try {
                // ── Nén ảnh trước khi upload ──────────────────
                let uploadBlob;
                try {
                    uploadBlob = await compressImage(file);
                    console.debug(
                        `[Avatar] Compressed: ${(file.size / 1024).toFixed(0)} KB → ` +
                        `${(uploadBlob.size / 1024).toFixed(0)} KB`
                    );
                } catch {
                    // Nếu nén thất bại (ảnh đặc biệt), dùng file gốc
                    uploadBlob = file;
                }

                // Upload blob đã nén
                const formData = new FormData();
                formData.append('avatar', uploadBlob, 'avatar.jpg');
                formData.append('_token', getCsrfToken());

                const res = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json();

                if (!res.ok) {
                    showToast(data.errors?.avatar?.[0] || data.message || 'Có lỗi xảy ra', 'error');
                    return;
                }

                // Replace blob preview with real Cloudinary URL
                setAvatarImage(data.avatar_url);
                setHeaderAvatar(data.avatar_url);
            } catch {
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            } finally {
                fileInput.value = '';
                if (spinner) spinner.remove();
            }
        });
    }

    // ═════════════════════════════════════════════════
    // 2. Save button: only name + bio (very fast, no file)
    // ═════════════════════════════════════════════════
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const name = document.getElementById('profileName').value.trim();
            if (!name) {
                showToast('Vui lòng nhập tên hiển thị', 'error');
                document.getElementById('profileName').focus();
                return;
            }

            const body = new URLSearchParams(new FormData(profileForm));
            try {
                const res = await fetch(profileForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: body,
                });
                const data = await res.json();

                if (!res.ok) {
                    const msg = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || 'Có lỗi xảy ra');
                    showToast(msg, 'error');
                    return;
                }

                // Update name everywhere instantly
                const headerName = document.querySelector('.fn-user-name');
                if (headerName) headerName.textContent = data.name;

                const profileCardName = document.querySelector('.fn-profile-name');
                if (profileCardName) profileCardName.textContent = data.name;

                // Cập nhật chữ cái đầu cho avatar mặc định (nếu chưa có ảnh)
                if (data.name && data.name.length > 0) {
                    const initialChar = data.name.charAt(0).toUpperCase();

                    const headerInitial = document.querySelector('.fn-user-btn .fn-user-avatar-initial');
                    if (headerInitial) headerInitial.textContent = initialChar;

                    const profileInitial = document.getElementById('profileAvatarInitial');
                    if (profileInitial) profileInitial.textContent = initialChar;
                }

            } catch {
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            }
        });
    }
});

// ═════════════════════════════════════════════════
// DOM Helpers
// ═════════════════════════════════════════════════

function setAvatarImage(url) {
    const wrapper = document.querySelector('.fn-avatar-wrapper');
    if (!wrapper) return;
    const initial = document.getElementById('profileAvatarInitial');
    let img = document.getElementById('profileAvatarImg');

    if (img) {
        img.src = url;
    } else if (initial) {
        const newImg = document.createElement('img');
        newImg.src = url;
        newImg.alt = 'Avatar';
        newImg.className = 'fn-avatar-img';
        newImg.id = 'profileAvatarImg';
        initial.replaceWith(newImg);
    } else {
        const newImg = document.createElement('img');
        newImg.src = url;
        newImg.alt = 'Avatar';
        newImg.className = 'fn-avatar-img';
        newImg.id = 'profileAvatarImg';
        wrapper.prepend(newImg);
    }
}

function setHeaderAvatar(url) {
    const btn = document.querySelector('.fn-user-btn');
    if (!btn) return;

    // Find current avatar element (could be div.fn-user-avatar-initial or img.fn-user-avatar)
    const current = btn.querySelector('.fn-user-avatar, .fn-user-avatar-initial');
    if (!current) return;

    if (current.tagName === 'IMG') {
        // Already an img, just update src
        current.src = url;
    } else {
        // Replace div with img
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Avatar';
        img.className = 'fn-user-avatar';
        current.replaceWith(img);
    }
}

// ═════════════════════════════════════════════════
// Password Change Panel – toggle, submit, eye
// ═════════════════════════════════════════════════

function toggleProfilePw(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    const icon = btn.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = isHidden ? 'visibility' : 'visibility_off';
}

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn   = document.getElementById('btnTogglePassword');
    const cancelBtn   = document.getElementById('btnCancelPassword');
    const panel       = document.getElementById('passwordCollapsePanel');
    const pwForm      = document.getElementById('changePasswordForm');

    if (!toggleBtn || !panel) return;

    // Toggle open/close
    toggleBtn.addEventListener('click', () => {
        const isOpen = panel.classList.contains('fn-pw-collapse--open');
        if (isOpen) {
            closePanel();
        } else {
            panel.classList.add('fn-pw-collapse--open');
            toggleBtn.querySelector('.material-symbols-outlined').textContent = 'lock_open';
            // Focus first input after animation
            setTimeout(() => {
                document.getElementById('currentPassword')?.focus();
            }, 300);
        }
    });

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => closePanel());
    }

    function closePanel() {
        panel.classList.remove('fn-pw-collapse--open');
        toggleBtn.querySelector('.material-symbols-outlined').textContent = 'lock_reset';
        if (pwForm) pwForm.reset();
        // Reset eye icons
        panel.querySelectorAll('.fn-pw-eye .material-symbols-outlined').forEach(icon => {
            icon.textContent = 'visibility_off';
        });
        panel.querySelectorAll('.fn-form-input').forEach(input => {
            input.type = 'password';
        });
    }

    // AJAX submit
    if (pwForm) {
        pwForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('btnSubmitPassword');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Đang xử lý…';

            try {
                const formData = new URLSearchParams(new FormData(pwForm));

                const res = await fetch('/profile/password', {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: formData,
                });

                const data = await res.json();

                if (!res.ok) {
                    const msg = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || 'Có lỗi xảy ra');
                    showToast(msg, 'error');
                    return;
                }

                showToast(data.message || 'Đổi mật khẩu thành công!', 'success');
                closePanel();
            } catch {
                showToast('Lỗi kết nối, vui lòng thử lại', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }
        });
    }
});

