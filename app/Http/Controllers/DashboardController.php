<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Number of notes per page on the dashboard. */
    private const PER_PAGE = 12;

    /** Maximum notes returned for a search query. */
    private const SEARCH_LIMIT = 50;

    public function index(Request $request): View
    {
        $user = $request->user();
        $notesQuery = $user->notes();

        if ($request->filled('q')) {
            $q = $request->q;
            $notesQuery->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        $isSearch = $request->filled('q');

        $notesQuery = $user->notes()->with(['labels', 'attachments', 'shares']);

        if ($isSearch) {
            // Use the model scope (FULLTEXT or LIKE fallback)
            $notesQuery->search($request->q)->defaultOrder();
            $recentNotes = $notesQuery->take(self::SEARCH_LIMIT)->get();
            $hasMoreNotes = false;
        } else {
            $notesQuery->defaultOrder();
            // Fetch one extra record to know if there's a next page
            $fetched = $notesQuery->take(self::PER_PAGE + 1)->get();
            $hasMoreNotes = $fetched->count() > self::PER_PAGE;
            $recentNotes = $fetched->take(self::PER_PAGE);
        }

        // Store the cursor for the next "load more" call
        $lastNote = $recentNotes->last();
        $nextCursor = ($hasMoreNotes && $lastNote)
            ? base64_encode($lastNote->updated_at->toIso8601String() . '|' . $lastNote->id)
            : null;

        return view('dashboard', [
            'recentNotes' => $notesQuery->clone()
                ->with(['labels', 'attachments', 'shares'])
                ->defaultOrder()
                ->take($isSearch ? 50 : 6)
                ->get(),
            'sharedNotes' => $user->sharedNotes()
                ->with(['note.labels', 'note.attachments', 'note.user:id,name,avatar_url'])
                ->latest()
                ->get(),
            'labels' => $user->labels()->orderBy('name')->get(),
            'searchQuery' => $request->q,
            'nextCursor' => $nextCursor,
            'hasMoreNotes' => $hasMoreNotes,
        ]);
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

        $query = $user->notes()
            ->with(['labels', 'attachments', 'shares'])
            ->defaultOrder();

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
     * AJAX endpoint: return notes filtered by one or more labels (label_ids[]).
     * Notes must have ALL selected labels (intersection/AND logic).
     */
    public function filterByLabel(Request $request): JsonResponse
    {
        $user = $request->user();
        $labelIds = array_filter(array_map('intval', (array) $request->input('label_ids', [])));

        $query = $user->notes()
            ->with(['labels', 'attachments', 'shares'])
            ->defaultOrder();

        // AND logic: note must have every selected label
        foreach ($labelIds as $labelId) {
            $query->whereHas('labels', fn($q) => $q->where('labels.id', $labelId));
        }

        $notes = $query->get()->map(fn($note) => $note->toCardArray());

        return response()->json(['notes' => $notes]);
    }
}
