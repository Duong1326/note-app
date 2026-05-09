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
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(
        private NoteService $noteService,
    ) {}

    /**
     * Display the full-page editor for creating a new note.
     * Reuses the notes.edit view with $note = null.
     */
    public function create(Request $request): View
    {
        return view('notes.edit', [
            'note'            => null,
            'labels'          => $request->user()->labels()->orderBy('name')->get(),
            'isOwner'         => true,
            'sharePermission' => null,
        ]);
    }

    /**
     * Display the full-page editor for an existing note.
     * Accessible by the note owner or users with edit permission.
     */
    public function edit(Request $request, Note $note): View
    {
        $user = $request->user();

        // Authorization: owner OR shared-edit user
        $isOwner = $note->user_id === $user->id;
        $sharePermission = null;

        if (!$isOwner) {
            $share = $note->shares()->where('shared_with_user_id', $user->id)->first();
            abort_if(!$share, 403, 'Bạn không có quyền truy cập ghi chú này.');
            $sharePermission = $share->permission; // 'view' | 'edit'
        }

        $note->load(['labels', 'attachments', 'shares']);

        return view('notes.edit', [
            'note'            => $note,
            'labels'          => $user->labels()->orderBy('name')->get(),
            'isOwner'         => $isOwner,
            'sharePermission' => $sharePermission,
        ]);
    }


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

        try {
            $this->noteService->delete($note, $deletedBy->id);
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

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
        abort_if(
            $note->user_id !== request()->user()->id,
            403,
            'Bạn không có quyền ghim ghi chú này.'
        );

        $note = $this->noteService->pin($note);

        if (request()->expectsJson()) {
            return response()->json([
                'success'    => true,
                'is_pinned'  => true,
                'updated_at' => $note->updated_at->diffForHumans(),
            ]);
        }

        return back();
    }

    public function unpin(Note $note): JsonResponse|RedirectResponse
    {
        abort_if(
            $note->user_id !== request()->user()->id,
            403,
            'Bạn không có quyền bỏ ghim ghi chú này.'
        );

        $note = $this->noteService->unpin($note);

        if (request()->expectsJson()) {
            return response()->json([
                'success'    => true,
                'is_pinned'  => false,
                'updated_at' => $note->updated_at->diffForHumans(),
            ]);
        }

        return back();
    }
}