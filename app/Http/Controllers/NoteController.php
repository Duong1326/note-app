<?php

namespace App\Http\Controllers;

use App\Events\NoteCreated;
use App\Events\NoteDeleted;
use App\Events\NoteUpdated;
use App\Models\Note;
use App\Models\Workspace;
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
    ) {
    }

    /**
     * Display the full-page editor for creating a new note.
     * Reuses the notes.edit view with $note = null.
     */
    public function create(Request $request): View
    {
        return view('notes.edit', [
            'note' => null,
            'labels' => $request->user()->labels()->orderBy('name')->get(),
            'isOwner' => true,
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

        // Authorization: note owner → full access
        $isOwner = $note->user_id === $user->id;
        $sharePermission = null;

        if (!$isOwner && $note->workspace_id) {
            $workspace = Workspace::find($note->workspace_id);

            // 1. Workspace owner has full access to all notes inside
            if ($workspace && $workspace->user_id === $user->id) {
                $isOwner = true;
            } else {
                // 2. Direct note-level share
                $noteShare = $note->shares()->where('shared_with_user_id', $user->id)->first();

                if ($noteShare) {
                    $sharePermission = $noteShare->permission;
                } elseif ($workspace) {
                    // 3. Workspace-level share (member of the workspace)
                    $wsShare = $workspace->shares()
                        ->where('shared_with_user_id', $user->id)
                        ->first();

                    abort_if(!$wsShare, 403, 'Bạn không có quyền truy cập ghi chú này.');

                    $sharePermission = $wsShare->permission; // 'read' | 'edit'

                    // If workspace edit member AND they own this note → treat as owner
                    if ($wsShare->permission === 'edit' && $note->user_id === $user->id) {
                        $isOwner = true;
                        $sharePermission = null;
                    }
                } else {
                    abort(403, 'Bạn không có quyền truy cập ghi chú này.');
                }
            }
        } elseif (!$isOwner) {
            // No workspace context — check note-level share only
            $noteShare = $note->shares()->where('shared_with_user_id', $user->id)->first();
            if (!$noteShare) {
                abort(403, 'Bạn không có quyền truy cập ghi chú này.');
            }
            $sharePermission = $noteShare->permission;
        }

        $note->load(['labels', 'attachments', 'shares']);

        return view('notes.edit', [
            'note' => $note,
            'labels' => $user->labels()->orderBy('name')->get(),
            'isOwner' => $isOwner,
            'sharePermission' => $sharePermission,
        ]);
    }

    /**
     * Lightweight permission pre-check — returns JSON only, no view rendered.
     * Called by JS before navigating to avoid landing on a 403 error page.
     */
    public function canEdit(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        // 1. Note creator
        if ($note->user_id === $user->id) {
            return response()->json(['allowed' => true]);
        }

        // 2. Workspace owner has full access to all notes inside their workspace
        $workspace = $note->workspace_id ? Workspace::find($note->workspace_id) : null;
        if ($workspace && $workspace->user_id === $user->id) {
            return response()->json(['allowed' => true]);
        }

        // 3. Direct note-level share
        $hasNoteShare = $note->shares()
            ->where('shared_with_user_id', $user->id)
            ->exists();
        if ($hasNoteShare) {
            return response()->json(['allowed' => true]);
        }

        // 4. Workspace-level share (any permission = can open the note)
        if ($workspace) {
            $hasWsShare = $workspace->shares()
                ->where('shared_with_user_id', $user->id)
                ->exists();
            if ($hasWsShare) {
                return response()->json(['allowed' => true]);
            }
        }

        return response()->json([
            'allowed' => false,
            'message' => 'Bạn không có quyền truy cập ghi chú này.',
        ], 403);
    }


    public function store(StoreNoteRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        // Guard: block note creation for read-only workspace members
        $activeWsId = session('active_workspace_id');
        if ($activeWsId) {
            $workspace = Workspace::find($activeWsId);
            if ($workspace && $workspace->user_id !== $user->id) {
                $share = $workspace->shares()
                    ->where('shared_with_user_id', $user->id)
                    ->first();
                if (!$share || $share->permission !== 'edit') {
                    $msg = 'Bạn chỉ có quyền đọc trong workspace này. Không thể tạo ghi chú.';
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => $msg], 403);
                    }
                    return back()->withErrors(['error' => $msg]);
                }
            }
        }

        $note = $this->noteService->create($user, $request->validated());

        // Broadcast to other workspace members so their dashboards update in real-time
        $note->loadMissing(['workspace.shares', 'labels', 'attachments']);
        NoteCreated::dispatch($note, $user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'note' => $note->toCardArray(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse|RedirectResponse
    {
        try {
            $data = $request->validated();
            // Always pass label_ids so that unchecking all labels (empty array) works
            if ($request->has('title')) {
                $data['label_ids'] = $request->input('label_ids', []);
            }
            $updated = $this->noteService->update($note, $data, $request->user()->id);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // Load note-level shares AND workspace shares so NoteUpdated can broadcast to all members
        $updated->loadMissing(['shares', 'workspace.shares']);

        if ($updated->wasChanged()) {
            NoteUpdated::dispatch($updated, $request->user());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'note' => $updated->toCardArray(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Note $note): JsonResponse|RedirectResponse
    {
        // Capture data before deletion (model will be gone after)
        $noteId = $note->id;
        $noteTitle = $note->title ?: 'Ghi chú không có tiêu đề';
        $deletedBy = request()->user();

        // Collect note-level shared user IDs
        $noteLevelIds = $note->shares()->pluck('shared_with_user_id')->toArray();

        // Collect workspace-level member IDs (so owner + members see real-time removal)
        $workspaceMemberIds = [];
        if ($note->workspace_id) {
            $workspace = \App\Models\Workspace::with('shares')->find($note->workspace_id);
            if ($workspace) {
                // Include the workspace owner
                if ($workspace->user_id !== $deletedBy->id) {
                    $workspaceMemberIds[] = $workspace->user_id;
                }
                // Include every workspace share member (except the deleter)
                foreach ($workspace->shares as $ws) {
                    if ($ws->shared_with_user_id !== $deletedBy->id) {
                        $workspaceMemberIds[] = $ws->shared_with_user_id;
                    }
                }
            }
        }

        $sharedUserIds = array_values(array_unique(array_merge($noteLevelIds, $workspaceMemberIds)));

        try {
            $this->noteService->delete($note, $deletedBy->id);
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // Notify all affected users in real-time so they remove the card
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
        $this->authorizePinAction($note, request()->user(), 'Bạn không có quyền ghim ghi chú này.');

        $note = $this->noteService->pin($note);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'is_pinned' => true,
                'updated_at' => $note->updated_at->diffForHumans(),
            ]);
        }

        return back();
    }

    public function unpin(Note $note): JsonResponse|RedirectResponse
    {
        $this->authorizePinAction($note, request()->user(), 'Bạn không có quyền bỏ ghim ghi chú này.');

        $note = $this->noteService->unpin($note);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'is_pinned' => false,
                'updated_at' => $note->updated_at->diffForHumans(),
            ]);
        }

        return back();
    }
    /**
     * Shared authorization check for pin/unpin: note owner OR workspace edit member.
     */
    private function authorizePinAction(Note $note, $user, string $message): void
    {
        $isOwner = $note->user_id === $user->id;
        $hasWsEdit = !$isOwner && $note->workspace_id &&
            Workspace::find($note->workspace_id)
                    ?->shares()->where('shared_with_user_id', $user->id)->where('permission', 'edit')->exists();

        abort_if(!$isOwner && !$hasWsEdit, 403, $message);
    }
}