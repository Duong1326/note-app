/**
 * live-search.js – Live search with 300ms debounce.
 *
 * Watches #globalSearch, calls GET /dashboard/search?q=<keyword> via AJAX,
 * then replaces the #notesContainer with matching results — no page reload.
 *
 * Depends on:
 *   app.js      (apiFetch, showToast)
 *   note-cards.js (buildNoteCardHtml)
 */

(function () {
    'use strict';

    /* ── State ───────────────────────────────────────── */
    let _debounceTimer  = null;
    let _lastQuery      = null;          // avoid duplicate fetches
    let _isSearchActive = false;         // true while showing search results

    /* ── Constants ───────────────────────────────────── */
    const DEBOUNCE_MS = 300;

    /* ── DOM refs (resolved after DOMContentLoaded) ──── */
    let $input, $clearBtn, $spinner, $sectionTitle, $loadMoreWrapper;

    /* ── Init ────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        $input         = document.getElementById('globalSearch');
        $clearBtn      = document.getElementById('searchClearBtn');
        $spinner       = document.getElementById('searchSpinner');
        $sectionTitle  = document.querySelector('.fn-section-title');
        $loadMoreWrapper = document.getElementById('loadMoreWrapper');

        if (!$input) return;  // not on the dashboard page

        // Live search on input
        $input.addEventListener('input', _onSearchInput);

        // Allow Escape to clear
        $input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') clearLiveSearch();
        });

        // If there was a search query on page load (e.g. back-navigation), run it
        const initial = $input.value.trim();
        if (initial) {
            _isSearchActive = true;
            // Trigger immediately without debounce
            _fetchResults(initial);
        }
    });

    /* ── Input handler ───────────────────────────────── */
    function _onSearchInput() {
        const q = $input.value.trim();

        // Toggle clear button visibility
        if ($clearBtn) $clearBtn.style.display = q ? 'flex' : 'none';

        clearTimeout(_debounceTimer);
        _debounceTimer = setTimeout(() => _fetchResults(q), DEBOUNCE_MS);
    }

    /* ── Fetch & render ──────────────────────────────── */
    async function _fetchResults(q) {
        // Skip if same query
        if (q === _lastQuery) return;
        _lastQuery = q;

        // Empty query → restore default dashboard view
        if (q === '') {
            _restoreDefaultView();
            return;
        }

        _setLoading(true);
        _isSearchActive = true;

        try {
            const url = `${window.FN_SEARCH_URL}?q=${encodeURIComponent(q)}`;
            const res  = await apiFetch(url, 'GET');
            if (!res.ok) throw new Error('Search failed');
            const data = await res.json();

            // Guard: query may have changed while we awaited
            if ($input.value.trim() !== q) return;

            _renderResults(data.notes ?? [], q);
        } catch (err) {
            showToast('Không thể tìm kiếm. Vui lòng thử lại.', 'error');
        } finally {
            _setLoading(false);
        }
    }

    /* ── Render search results into #notesContainer ──── */
    function _renderResults(notes, q) {
        // Update section header
        if ($sectionTitle) {
            $sectionTitle.innerHTML = notes.length > 0
                ? `Kết quả cho "<em>${escapeHtml(q)}</em>" <span style="font-size:0.8em;font-weight:400;opacity:0.7;">(${notes.length})</span>`
                : `Tìm kiếm "<em>${escapeHtml(q)}</em>"`;
        }

        // Hide Load More (irrelevant during search)
        if ($loadMoreWrapper) $loadMoreWrapper.style.display = 'none';

        let container = document.getElementById('notesContainer');

        if (notes.length === 0) {
            // Show empty state
            const emptyHtml = `
                <div class="col-12">
                    <div class="text-center py-5 text-muted fn-empty-state">
                        <span class="material-symbols-outlined d-block mb-3">search_off</span>
                        <p class="small opacity-75">Không tìm thấy kết quả nào cho "<strong>${escapeHtml(q)}</strong>".</p>
                    </div>
                </div>`;

            if (container) {
                container.innerHTML = emptyHtml;
            } else {
                // Container might have been removed (all notes deleted)
                _ensureContainer();
                document.getElementById('notesContainer').innerHTML = emptyHtml;
            }
            return;
        }

        // Build or reuse container
        if (!container) container = _ensureContainer();

        // Render note cards — reuse the shared builder from note-cards.js
        container.innerHTML = notes.map(note => buildNoteCardHtml(note)).join('');

        // Restore current view preference (grid/list)
        const savedView = localStorage.getItem('notesView') || 'grid';
        container.classList.toggle('fn-view-list', savedView === 'list');
    }

    /* ── Restore default view (empty query) ─────────── */
    function _restoreDefaultView() {
        _isSearchActive = false;
        _lastQuery      = null;

        // Reload the page to let the server render the full default dashboard.
        // This is the simplest approach that preserves pinned order, load-more, etc.
        window.location.href = window.location.pathname;
    }

    /* ── Public: clear search ────────────────────────── */
    window.clearLiveSearch = function () {
        $input.value = '';
        if ($clearBtn) $clearBtn.style.display = 'none';
        clearTimeout(_debounceTimer);
        _restoreDefaultView();
    };

    /* ── Helpers ─────────────────────────────────────── */
    function _setLoading(on) {
        if ($spinner) $spinner.style.display = on ? 'inline-block' : 'none';
    }

    function _ensureContainer() {
        let container = document.getElementById('notesContainer');
        if (container) return container;

        // Remove empty state placeholder if present
        document.querySelector('.fn-empty-state')?.closest('.col-12')?.remove();

        container = document.createElement('div');
        container.className = 'row g-3';
        container.id = 'notesContainer';

        // Insert before #loadMoreWrapper or at the end of the notes col
        const wrapper = $loadMoreWrapper?.closest('.col-12') ??
            document.querySelector('.fn-dashboard > .row > .col-12');
        wrapper?.appendChild(container);

        return container;
    }
})();
