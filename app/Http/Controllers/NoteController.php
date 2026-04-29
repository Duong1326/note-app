<?php

namespace App\Http\Controllers;

use App\Events\NoteDeleted;
use App\Events\NoteUpdated;
use App\Models\Note;
use App\Services\NoteService;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class NoteController extends Controller
{
    public function __construct(
        private NoteService $noteService,
    ) {}

    public function store(StoreNoteRequest $request): JsonResponse|RedirectResponse
    {
        $note = $this->noteService->create($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'note'    => $note->toCardArray(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse|RedirectResponse
    {
        try {
            $updated = $this->noteService->update($note, $request->validated(), $request->user()->id);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // Broadcast real-time update to owner + all share recipients
        $updated->loadMissing('shares');
        NoteUpdated::dispatch($updated, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'note'    => $updated->toCardArray(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Note $note): JsonResponse|RedirectResponse
    {
        // Capture data before deletion (model will be gone after)
        $noteId        = $note->id;
        $noteTitle     = $note->title ?: 'Ghi chú không có tiêu đề';
        $sharedUserIds = $note->shares()->pluck('shared_with_user_id')->toArray();
        $deletedBy     = request()->user();

        $this->noteService->delete($note);

        // Notify shared users in real-time so they remove the card
        if (!empty($sharedUserIds)) {
            NoteDeleted::dispatch($noteId, $noteTitle, $deletedBy, $sharedUserIds);
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard');
    }

    public function pin(Note $note): JsonResponse|RedirectResponse
    {
        $this->noteService->pin($note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'is_pinned' => true]);
        }

        return back();
    }

    public function unpin(Note $note): JsonResponse|RedirectResponse
    {
        $this->noteService->unpin($note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'is_pinned' => false]);
        }

        return back();
    }
}