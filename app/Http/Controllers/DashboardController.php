<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /** Number of notes per page on the dashboard. */
    private const PER_PAGE = 12;

    /** Maximum notes returned for a search query. */
    private const SEARCH_LIMIT = 50;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSearch = $request->filled('q');

        // ── Shared-with-me view ──
        if ($request->get('view') === 'shared') {
            $sharedNotes = $user->sharedNotes()
                ->with(['note.labels', 'note.attachments', 'note.user:id,name,avatar_url'])
                ->latest()
                ->get();

            return response(view('dashboard', [
                'recentNotes'      => collect(),
                'sharedNotes'      => $sharedNotes,
                'labels'           => $user->labels()->orderBy('name')->get(),
                'searchQuery'      => null,
                'nextCursor'       => null,
                'hasMoreNotes'     => false,
                'activeWorkspace'  => null,
                'isWorkspaceOwner' => false,
                'workspaceShare'   => null,
                'isSharedView'     => true,
                'canCreateNote'    => false,   // read-only view
            ]))->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        // Determine active workspace
        $defaultWs = $user->ensureDefaultWorkspace();
        $activeWsId = session('active_workspace_id', $defaultWs->id);

        // Check if user owns this workspace or has shared access
        $activeWorkspace = \App\Models\Workspace::find($activeWsId);
        $isWorkspaceOwner = $activeWorkspace && $activeWorkspace->user_id === $user->id;
        $workspaceShare = null;

        if (!$isWorkspaceOwner && $activeWorkspace) {
            $workspaceShare = $activeWorkspace->shares()
                ->where('shared_with_user_id', $user->id)
                ->first();

            if (!$workspaceShare) {
                // Fallback to default workspace
                $activeWsId = $defaultWs->id;
                $activeWorkspace = $defaultWs;
                $isWorkspaceOwner = true;
                session(['active_workspace_id' => $activeWsId]);
            }
        }

        // Build notes query: always show ALL notes inside the workspace,
        // regardless of who created them (owner or any member).
        if ($isWorkspaceOwner) {
            $notesQuery = \App\Models\Note::where('workspace_id', $activeWsId)
                ->with(['labels', 'attachments', 'shares', 'user:id,name,avatar_url']);
        } else {
            // Shared workspace: show ALL notes inside it
            $notesQuery = \App\Models\Note::where('workspace_id', $activeWsId)
                ->with(['labels', 'attachments', 'shares', 'user:id,name,avatar_url']);
        }


        if ($isSearch) {
            $notesQuery->search($request->q)->defaultOrder();
            $recentNotes = $notesQuery->take(self::SEARCH_LIMIT)->get();
            $hasMoreNotes = false;
        } else {
            $notesQuery->defaultOrder();
            $fetched = $notesQuery->take(self::PER_PAGE + 1)->get();
            $hasMoreNotes = $fetched->count() > self::PER_PAGE;
            $recentNotes = $fetched->take(self::PER_PAGE);
        }

        // Store the cursor for the next "load more" call
        $lastNote = $recentNotes->last();
        $nextCursor = ($hasMoreNotes && $lastNote)
            ? base64_encode($lastNote->updated_at->toIso8601String() . '|' . $lastNote->id)
            : null;

        // Can the current user create notes in this workspace?
        $canCreateNote = $isWorkspaceOwner
            || ($workspaceShare && $workspaceShare->permission === 'edit');

        return response(view('dashboard', [
            'recentNotes'      => $recentNotes,
            'sharedNotes'      => $user->sharedNotes()
                ->with(['note.labels', 'note.attachments', 'note.user:id,name,avatar_url'])
                ->latest()
                ->get(),
            'labels'           => $user->labels()->orderBy('name')->get(),
            'searchQuery'      => $request->q,
            'nextCursor'       => $nextCursor,
            'hasMoreNotes'     => $hasMoreNotes,
            'activeWorkspace'  => $activeWorkspace,
            'isWorkspaceOwner' => $isWorkspaceOwner,
            'workspaceShare'   => $workspaceShare,
            'canCreateNote'    => $canCreateNote,
            'isSharedView'     => false,
        ]))->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * AJAX "Load More" endpoint.
     * GET /dashboard/load-more?cursor=<base64>
     *
     * Returns the next page of PER_PAGE notes after the given cursor,
     * plus a new cursor for the subsequent page.
     */
    public function loadMore(Request $request): JsonResponse
    {
        $user = $request->user();
        $cursor = $request->input('cursor');
        $activeWsId = session('active_workspace_id');

        // Determine if user owns the workspace or is viewing a shared one
        $workspace = $activeWsId ? \App\Models\Workspace::find($activeWsId) : null;
        $isOwner = $workspace && $workspace->user_id === $user->id;

        // Always show ALL notes in the workspace (from any creator: owner or members)
        if ($workspace) {
            $query = \App\Models\Note::where('workspace_id', $activeWsId)
                ->with(['labels', 'attachments', 'shares', 'user:id,name,avatar_url'])
                ->defaultOrder();
        } else {
            return response()->json(['notes' => [], 'hasMoreNotes' => false, 'nextCursor' => null]);
        }

        if ($cursor) {
            $decoded = base64_decode($cursor, strict: true);
            if ($decoded && str_contains($decoded, '|')) {
                [$cursorDate, $cursorId] = explode('|', $decoded, 2);

                // Cursor-based pagination: notes strictly "older" than the cursor
                // (same updated_at but lower id counts as older)
                $query->where(function ($q) use ($cursorDate, $cursorId) {
                    $q->where('updated_at', '<', $cursorDate)
                        ->orWhere(function ($q2) use ($cursorDate, $cursorId) {
                            $q2->where('updated_at', '=', $cursorDate)
                                ->where('id', '<', (int) $cursorId);
                        });
                });
            }
        }

        $fetched = $query->take(self::PER_PAGE + 1)->get();
        $hasMore = $fetched->count() > self::PER_PAGE;
        $notes = $fetched->take(self::PER_PAGE);

        $lastNote = $notes->last();
        $nextCursor = ($hasMore && $lastNote)
            ? base64_encode($lastNote->updated_at->toIso8601String() . '|' . $lastNote->id)
            : null;

        return response()->json([
            'notes' => $notes->map(fn($note) => $note->toCardArray()),
            'hasMoreNotes' => $hasMore,
            'nextCursor' => $nextCursor,
        ]);
    }

    /**
     * AJAX live-search endpoint.
     * GET /dashboard/search?q=<keyword>
     *
     * Returns notes matching the keyword (title OR content) as JSON cards.
     * Used by the live-search JS (300 ms debounce) so the page never reloads.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $q    = trim((string) $request->input('q', ''));
        $activeWsId = session('active_workspace_id');

        $workspace = $activeWsId ? \App\Models\Workspace::find($activeWsId) : null;
        $isOwner = $workspace && $workspace->user_id === $user->id;

        // Always query all notes in the workspace regardless of who created them
        if ($workspace) {
            $query = \App\Models\Note::where('workspace_id', $activeWsId)
                ->with(['labels', 'attachments', 'shares', 'user:id,name,avatar_url'])
                ->defaultOrder();
        } else {
            return response()->json(['notes' => [], 'query' => $q, 'total' => 0]);
        }

        if ($q !== '') {
            $query->search($q);
        }

        $notes = $query->take(self::SEARCH_LIMIT)->get()
            ->map(fn ($note) => $note->toCardArray());

        return response()->json([
            'notes'       => $notes,
            'query'       => $q,
            'total'       => $notes->count(),
        ]);
    }

    public function filterByLabel(Request $request): JsonResponse
    {
        $user = $request->user();
        $labelIds = array_filter(array_map('intval', (array) $request->input('label_ids', [])));
        $activeWsId = session('active_workspace_id');

        $workspace = $activeWsId ? \App\Models\Workspace::find($activeWsId) : null;
        $isOwner = $workspace && $workspace->user_id === $user->id;

        // Always query all notes in the workspace regardless of who created them
        if ($workspace) {
            $query = \App\Models\Note::where('workspace_id', $activeWsId)
                ->with(['labels', 'attachments', 'shares', 'user:id,name,avatar_url'])
                ->defaultOrder();
        } else {
            return response()->json(['notes' => []]);
        }

        // AND logic: note must have every selected label
        foreach ($labelIds as $labelId) {
            $query->whereHas('labels', fn($q) => $q->where('labels.id', $labelId));
        }

        $notes = $query->get()->map(fn($note) => $note->toCardArray());

        return response()->json(['notes' => $notes]);
    }
}
