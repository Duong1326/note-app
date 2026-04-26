<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Events\NoteUpdated;
use App\Models\Note;
use App\Services\LabelService;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteControler extends Controller
{
    public function __construct(
        private NoteService $noteService,
        private LabelService $labelService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['q', 'label_ids']);

        if (isset($filters['label_ids']) && is_string($filters['label_ids'])) {
            $filters['label_ids'] = array_map('intval', explode(',', $filters['label_ids']));
        }

        $notes = $this->noteService->listForUser($user, $filters);
        $labels = $this->labelService->listForUser($user);

        return view('notes.index', compact('notes', 'labels', 'filters'));
    }

    public function store(StoreNoteRequest $request): JsonResponse|RedirectResponse
    {
        $note = $this->noteService->create($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'note' => [
                    'id'          => $note->id,
                    'title'       => $note->title,
                    'content'     => $note->content,
                    'is_pinned'   => $note->is_pinned,
                    'updated_at'  => $note->updated_at?->diffForHumans(),
                    'labels'      => $note->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
                    'attachments' => $note->attachments->map(fn($a) => [
                        'id'            => $a->id,
                        'url'           => $a->secure_url,
                        'thumbnail_url' => $a->thumbnailUrl(400),
                    ]),
                ],
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
                'note' => [
                    'id'          => $updated->id,
                    'title'       => $updated->title,
                    'content'     => $updated->content,
                    'is_pinned'   => $updated->is_pinned,
                    'updated_at'  => $updated->updated_at?->diffForHumans(),
                    'labels'      => $updated->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
                    'attachments' => $updated->attachments->map(fn($a) => [
                        'id'            => $a->id,
                        'url'           => $a->secure_url,
                        'thumbnail_url' => $a->thumbnailUrl(400),
                    ]),
                ],
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Note $note): JsonResponse|RedirectResponse
    {
        $this->noteService->delete($note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard');
    }

    public function pin(Note $note): JsonResponse|RedirectResponse
    {
        $note = $this->noteService->pin($note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'is_pinned' => true]);
        }

        return back();
    }

    public function unpin(Note $note): JsonResponse|RedirectResponse
    {
        $note = $this->noteService->unpin($note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'is_pinned' => false]);
        }

        return back();
    }
}