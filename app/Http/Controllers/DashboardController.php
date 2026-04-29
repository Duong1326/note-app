<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user       = $request->user();
        $notesQuery = $user->notes();

        if ($request->filled('q')) {
            $q = $request->q;
            $notesQuery->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            });
        }

        $isSearch = $request->filled('q');

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
            'labels'      => $user->labels()->orderBy('name')->get(),
            'searchQuery' => $request->q,
        ]);
    }

    /**
     * AJAX endpoint: return notes filtered by one or more labels (label_ids[]).
     * Notes must have ALL selected labels (intersection/AND logic).
     */
    public function filterByLabel(Request $request): JsonResponse
    {
        $user     = $request->user();
        $labelIds = array_filter(array_map('intval', (array) $request->input('label_ids', [])));

        $query = $user->notes()
            ->with(['labels', 'attachments', 'shares'])
            ->defaultOrder();

        // AND logic: note must have every selected label
        foreach ($labelIds as $labelId) {
            $query->whereHas('labels', fn ($q) => $q->where('labels.id', $labelId));
        }

        $notes = $query->get()->map(fn ($note) => $note->toCardArray());

        return response()->json(['notes' => $notes]);
    }
}
