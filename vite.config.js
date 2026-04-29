import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import legacy from '@vitejs/plugin-legacy';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

// ESM polyfill for __dirname
const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Custom Vite plugin that concatenates a list of plain vanilla-JS files
 * (which rely on global function declarations) into a single virtual module.
 *
 * Because the files use global `function` declarations and `var` statements,
 * we wrap the concatenated output in an IIFE and explicitly assign every
 * public function onto `window` so that inline onclick="..." handlers keep
 * working after bundling.
 */
function concatVanillaJs(id, files, windowExports = []) {
    const virtualId = `\0virtual:${id}`;

    return {
        name: `concat-vanilla-js:${id}`,

        resolveId(source) {
            if (source === `virtual:${id}`) return virtualId;
        },

        load(id) {
            if (id !== virtualId) return;

            // Concatenate all source files
            const combined = files
                .map(f => {
                    try {
                        return `/* === ${f} === */\n` + readFileSync(resolve(__dirname, f), 'utf8');
                    } catch {
                        console.warn(`[concat-vanilla-js] Could not read ${f}`);
                        return '';
                    }
                })
                .join('\n\n');

            // Build window-export block for every public function
            const exports = windowExports
                .map(fn => `if (typeof ${fn} !== 'undefined') window.${fn} = ${fn};`)
                .join('\n');

            // Wrap in IIFE – globals declared inside will NOT leak to real
            // window scope, so we re-attach them explicitly at the end.
            return `(function () {\n${combined}\n\n// ── Window exports ──\n${exports}\n})();`;
        },
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// Public functions that Blade inline onclick="" handlers call directly.
// Every function listed here will be attached to window.xxx after bundling.
// ─────────────────────────────────────────────────────────────────────────────

const CORE_EXPORTS = [
    // app.js
    'getCsrfToken', 'showToast', 'toggleSidebar', 'closeSidebar',
    'escapeHtml', 'escapeAttr', 'apiFetch',
    // echo-init.js
    'toggleNotificationDropdown', 'markAllAsRead', 'closeNotificationDropdown',
];

const DASHBOARD_EXPORTS = [
    // note-lock.js
    'requireUnlock', 'getLockToken', 'clearLockToken',
    'openEnableLockModal', 'openChangeLockModal', 'openDisableLockModal',
    // note-cards.js
    'buildNoteCardHtml', 'prependNoteCard', 'patchNoteCard', 'patchPinCard',
    'removeNoteCard', 'moveCardAfterPin', 'moveCardToTopOfUnpinned',
    'contentToExcerpt', 'updateCardLabels', 'updateCardThumbnail',
    // note-attachments.js
    'renderPendingPreviews', 'showAttachmentSection',
    // note-slash-menu.js
    'showSlashMenu', 'hideSlashMenu', 'handleSlashMenuKeydown',
    'handleSlashMenuInput', '_slashMenuVisible',
    // note-modal.js
    'openNewNoteModal', 'closeNewNoteModal', 'openEditNoteModal', 'submitNoteForm',
    // notes.js
    'setNotesView', 'deleteNoteAjax', 'togglePinAjax', 'loadMoreNotes',
    // labels.js
    'filterNotesByLabel', 'createLabel', 'toggleAddLabelForm', 'deleteLabel',
    'startRenameLabel', 'saveRenameLabel', 'cancelRenameLabel', 'onSidebarLabelBlur',
    // note-share.js
    'openShareModal',
    // shared-notes.js
    'openSharedNoteModal', 'closeSharedNoteModal', 'saveSharedNote',
    'refreshSharedNotesSection',
];

const PROFILE_EXPORTS = [
    // profile.js — inline handlers inside profile.blade.php
    'submitAvatarForm', 'triggerAvatarInput',
];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ── CSS bundles ──
                'resources/css/app.css',       // Bootstrap + all layout CSS
                'resources/css/dashboard.css', // Dashboard-specific CSS
                'resources/css/auth.css',      // Auth-page CSS
                'resources/css/profile.css',   // Profile-page CSS

                // ── JS bundles ──
                'resources/js/bootstrap.js',   // Bootstrap JS (selective)
                'virtual:core-scripts',        // app.js + echo-init.js
                'virtual:dashboard-scripts',   // All 10 dashboard JS files
                'virtual:profile-scripts',     // Profile JS
            ],
            refresh: true,
        }),

        // Bundle Bootstrap JS separately (it IS an ES module)
        // —handled via resources/js/bootstrap.js import

        // Core scripts (available on every page)
        concatVanillaJs('core-scripts', [
            'public/assets/js/app.js',
            'public/assets/js/echo-init.js',
        ], CORE_EXPORTS),

        // Dashboard scripts (only loaded on dashboard)
        concatVanillaJs('dashboard-scripts', [
            'public/assets/js/note-lock.js',
            'public/assets/js/note-cards.js',
            'public/assets/js/note-attachments.js',
            'public/assets/js/note-slash-menu.js',
            'public/assets/js/note-modal.js',
            'public/assets/js/notes.js',
            'public/assets/js/labels.js',
            'public/assets/js/note-share.js',
            'public/assets/js/shared-notes.js',
        ], DASHBOARD_EXPORTS),

        // Profile scripts
        concatVanillaJs('profile-scripts', [
            'public/assets/js/profile.js',
        ], PROFILE_EXPORTS),

        // Transpile + polyfill for older browsers
        legacy({
            targets: ['defaults', 'not IE 11'],
        }),
    ],

    build: {
        // Use terser for better minification
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,  // Remove console.log in production
                drop_debugger: true,
            },
        },
        rollupOptions: {
            output: {
                // Group vendor (Bootstrap) into a separate chunk for long-term caching
                manualChunks(id) {
                    if (id.includes('node_modules/bootstrap')) {
                        return 'vendor-bootstrap';
                    }
                },
            },
        },
    },

    css: {
        devSourcemap: true,
    },
});
