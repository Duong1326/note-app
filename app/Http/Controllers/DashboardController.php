<?php

namespace App\Http\Controllers;

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
            'recentNotes' => $notesQuery->clone()->with(['labels', 'attachments', 'shares'])->defaultOrder()->take($isSearch ? 50 : 6)->get(),
            'pinnedNotes' => $notesQuery->clone()->where('is_pinned', true)->defaultOrder()->get(),
            'totalNotes'  => $notesQuery->clone()->count(),
            'weeklyNotes' => $notesQuery->clone()->where('created_at', '>=', now()->subWeek())->count(),
            'sharedNotes' => $user->sharedNotes()->with(['note.labels', 'note.attachments', 'note.user:id,name,avatar_url'])->latest()->get(),
            'labels'      => $user->labels()->orderBy('name')->get(),
            'searchQuery' => $request->q,
        ]);
    }
}
