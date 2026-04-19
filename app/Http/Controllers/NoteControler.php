<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Models\Note;
use App\Services\LabelService;
use App\Services\NoteService;
use App\Services\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteControler extends Controller
{
    public function __construct(
        private NoteService $noteService,
        private LabelService $labelService,
        private PreferenceService $prefService,
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
        $preferences = $this->prefService->getForUser($user);

        return view('notes.index', compact('notes', 'labels', 'preferences', 'filters'));
    }

    public function store(StoreNoteRequest $request): RedirectResponse
    {
        $note = $this->noteService->create($request->user(), $request->validated());
        return redirect()->route('dashboard');
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse|RedirectResponse
    {
        $updated = $this->noteService->update($note, $request->validated(), $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'updated_at' => $updated->updated_at?->toIso8601String(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->noteService->delete($note);

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