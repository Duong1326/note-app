<?php

namespace App\Http\Controllers;

use App\Events\NoteShared;
use App\Events\SharePermissionChanged;
use App\Events\ShareRevoked;
use App\Http\Requests\Note\ShareNoteRequest;
use App\Http\Requests\Note\UpdateShareRequest;
use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteShareController extends Controller
{
    // ──────────────────────────────────────────────
    // List shares for a note
    // ──────────────────────────────────────────────

    /**
     * GET /notes/{note}/shares
     * Return all share records for this note (owner only).
     */
    public function index(Request $request, Note $note): JsonResponse
    {
        $this->authorizeOwner($request, $note);

        $shares = $note->shares()->with('sharedWith:id,name,email,avatar_url')->get()
            ->map(fn (NoteShare $s) => [
                'id'         => $s->id,
                'user_id'    => $s->shared_with_user_id,
                'name'       => $s->sharedWith->name,
                'email'      => $s->sharedWith->email,
                'avatar_url' => $s->sharedWith->avatarUrl(),
                'permission' => $s->permission,
            ]);

        return response()->json(['success' => true, 'shares' => $shares]);
    }

    // ──────────────────────────────────────────────
    // Create / add new recipients
    // ──────────────────────────────────────────────

    /**
     * POST /notes/{note}/shares
     * Share a note with one or more email addresses.
     */
    public function store(ShareNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorizeOwner($request, $note);

        $permission = $request->validated('permission');
        $emails     = $request->validated('emails');
        $created    = [];
        $skipped    = [];

        foreach ($emails as $email) {
            $recipient = User::where('email', $email)->first();

            // Cannot share with yourself
            if ($recipient->id === $request->user()->id) {
                $skipped[] = $email;
                continue;
            }

            // Upsert: if already shared, just update permission
            $share = NoteShare::updateOrCreate(
                [
                    'note_id'              => $note->id,
                    'shared_with_user_id'  => $recipient->id,
                ],
                [
                    'owner_id'   => $note->user_id,
                    'permission' => $permission,
                ]
            );

            $created[] = [
                'id'         => $share->id,
                'user_id'    => $recipient->id,
                'name'       => $recipient->name,
                'email'      => $recipient->email,
                'avatar_url' => $recipient->avatarUrl(),
                'permission' => $share->permission,
            ];

            // Broadcast real-time notification to the recipient
            broadcast(new NoteShared($share, $note, $request->user()))->toOthers();
        }

        return response()->json([
            'success' => true,
            'shares'  => $created,
            'skipped' => $skipped,
            'message' => count($created) > 0
                ? 'Đã chia sẻ ghi chú thành công.'
                : 'Không có người dùng nào được thêm mới.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Update permission
    // ──────────────────────────────────────────────

    /**
     * PUT /notes/{note}/shares/{share}
     * Update the permission level of an existing share.
     */
    public function update(UpdateShareRequest $request, Note $note, NoteShare $share): JsonResponse
    {
        $this->authorizeOwner($request, $note);
        abort_if($share->note_id !== $note->id, 404);

        $oldPermission = $share->permission;
        $share->update(['permission' => $request->validated('permission')]);

        // Broadcast permission change to the affected user
        broadcast(new SharePermissionChanged($share, $request->user(), $oldPermission))->toOthers();

        return response()->json([
            'success'    => true,
            'permission' => $share->permission,
            'message'    => 'Đã cập nhật quyền chia sẻ.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Revoke access
    // ──────────────────────────────────────────────

    /**
     * DELETE /notes/{note}/shares/{share}
     * Revoke a single user's access.
     */
    public function destroy(Request $request, Note $note, NoteShare $share): JsonResponse
    {
        $this->authorizeOwner($request, $note);
        abort_if($share->note_id !== $note->id, 404);

        $sharedWithUserId = $share->shared_with_user_id;
        $noteTitle = $note->title;
        $share->delete();

        // Broadcast revocation to the affected user
        broadcast(new ShareRevoked($sharedWithUserId, $note->id, $noteTitle, $request->user()))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Đã thu hồi quyền truy cập.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Fetch note data for shared view/edit modal
    // ──────────────────────────────────────────────

    /**
     * GET /notes/{note}/shared-view
     * Returns note content for a shared user to display in view/edit modal.
     */
    public function sharedView(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        // Owner or shared user can access
        $share = null;
        if ($note->user_id !== $user->id) {
            $share = $note->shareFor($user->id);
            abort_if(!$share, 403, 'Bạn không có quyền truy cập ghi chú này.');
        }

        return response()->json([
            'success'    => true,
            'permission' => $share?->permission ?? 'edit', // owner always has edit
            'note' => [
                'id'          => $note->id,
                'title'       => $note->title,
                'content'     => $note->content,
                'is_pinned'   => $note->is_pinned,
                'updated_at'  => $note->updated_at?->diffForHumans(),
                'labels'      => $note->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
                'attachments' => $note->load('attachments')->attachments->map(fn($a) => [
                    'id'            => $a->id,
                    'url'           => $a->secure_url,
                    'thumbnail_url' => $a->thumbnailUrl(400),
                ]),
                'owner'       => [
                    'name'       => $note->user->name ?? '',
                    'avatar_url' => $note->user?->avatarUrl(),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // Shared notes as card data (for real-time insert)
    // ──────────────────────────────────────────────

    /**
     * GET /shared-notes/cards
     * Returns all shared notes formatted for DOM card rendering.
     * Used by Echo to refresh the shared section after receiving NoteShared event.
     */
    public function sharedWithMeCards(Request $request): JsonResponse
    {
        $user = $request->user();

        $shares = NoteShare::where('shared_with_user_id', $user->id)
            ->with([
                'note' => fn($q) => $q->with(['attachments', 'labels', 'user:id,name,avatar_url']),
            ])
            ->latest()
            ->get()
            ->map(fn(NoteShare $s) => [
                'share_id'   => $s->id,
                'permission' => $s->permission,
                'note'       => [
                    'id'          => $s->note->id,
                    'title'       => $s->note->title,
                    'content'     => $s->note->content,
                    'updated_at'  => $s->note->updated_at?->diffForHumans(),
                    'labels'      => $s->note->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
                    'attachments' => $s->note->attachments->map(fn($a) => [
                        'id'            => $a->id,
                        'url'           => $a->secure_url,
                        'thumbnail_url' => $a->thumbnailUrl(400),
                    ]),
                    'owner'       => [
                        'name'       => $s->note->user->name ?? '',
                        'avatar_url' => $s->note->user?->avatarUrl(),
                    ],
                ],
            ]);

        return response()->json(['success' => true, 'shared_notes' => $shares]);
    }

    // ──────────────────────────────────────────────
    // Shared-with-me list
    // ──────────────────────────────────────────────

    /**
     * GET /shared-notes
     * Return notes shared with the current user.
     */
    public function sharedWithMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $shares = NoteShare::where('shared_with_user_id', $user->id)
            ->with([
                'note' => fn ($q) => $q->with(['attachments', 'labels', 'user:id,name,avatar_url']),
            ])
            ->latest()
            ->get()
            ->map(fn (NoteShare $s) => [
                'share_id'   => $s->id,
                'permission' => $s->permission,
                'note'       => [
                    'id'          => $s->note->id,
                    'title'       => $s->note->title,
                    'content'     => $s->note->content,
                    'updated_at'  => $s->note->updated_at?->diffForHumans(),
                    'labels'      => $s->note->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
                    'attachments' => $s->note->attachments->map(fn ($a) => [
                        'id'            => $a->id,
                        'url'           => $a->secure_url,
                        'thumbnail_url' => $a->thumbnailUrl(400),
                    ]),
                    'owner'       => [
                        'name'       => $s->note->user->name,
                        'avatar_url' => $s->note->user->avatarUrl(),
                    ],
                ],
            ]);

        return response()->json(['success' => true, 'shared_notes' => $shares]);
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function authorizeOwner(Request $request, Note $note): void
    {
        abort_if(
            $request->user()->id !== $note->user_id,
            403,
            'Bạn không có quyền quản lý chia sẻ của ghi chú này.'
        );
    }
}
