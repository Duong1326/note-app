/**
 * resources/js/bootstrap.js
 *
 * Import only the Bootstrap JS components actually used by the app.
 * This avoids loading the full 207 KB bootstrap.bundle.js.
 *
 * Components used:
 *  - Dropdown  → note card "more_vert" menu, user avatar menu
 *  - Collapse  → (future use / Bootstrap utilities)
 *
 * NOT needed (so not imported):
 *  - Modal      → we use custom fn-modal-overlay modals
 *  - Tooltip    → not used
 *  - Toast      → we use custom fn-toast
 *  - Carousel   → not used
 *  - Offcanvas  → not used
 */
import Dropdown from 'bootstrap/js/dist/dropdown';
import Collapse from 'bootstrap/js/dist/collapse';

// Expose on window so any legacy code that does
// `new bootstrap.Dropdown(el)` still works.
window.bootstrap = { Dropdown, Collapse };
