/**
 * app.js – Global helpers available on every page.
 * Loaded in layouts/app.blade.php after Bootstrap JS.
 */

// ── CSRF Token ────────────────────────────────────
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}

// ── Toast Notifications ───────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const icon = type === 'success' ? 'check_circle' : 'error';

    const toast = document.createElement('div');
    toast.className = `fn-toast fn-toast-${type}`;
    toast.innerHTML = `
        <span class="material-symbols-outlined" style="font-size:20px;">${icon}</span>
        <span>${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('fn-toast-exit');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }, 3000);
}

// ── Sidebar Toggle ────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('sidebarOverlay').classList.remove('show');
}

window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) closeSidebar();
});

// ── HTML Escape Helpers ───────────────────────────
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function escapeAttr(str) {
    return (str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ── API Fetch Helper ──────────────────────────────
async function apiFetch(url, method = 'GET', body = null, extraHeaders = {}) {
    const headers = {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
        ...extraHeaders,
    };
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    return fetch(url, {
        method,
        headers,
        body: body instanceof FormData ? body : (body?.toString() ?? undefined),
    });
}

// ── BFCache Handler ───────────────────────────────
// Fix stale data when navigating back via the browser's Back button (bfcache)
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
