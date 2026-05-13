<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class NoteService
{
    /**
     * Create a new note for the given user.
     */
    public function create(User $user, array $data): Note
    {
        try {
            $note = DB::transaction(function () use ($user, $data) {
                // Assign to active workspace (or default)
                $workspaceId = $data['workspace_id']
                    ?? session('active_workspace_id')
                    ?? $user->ensureDefaultWorkspace()->id;

                $note = $user->notes()->create([
                    'title'        => $data['title'],
                    'content'      => $data['content'] ?? null,
                    'is_pinned'    => $data['is_pinned'] ?? false,
                    'workspace_id' => $workspaceId,
                ]);

                if (!empty($data['label_ids'])) {
                    $note->labels()->sync($data['label_ids']);
                }

                return $note;
            });

            return $note->load(['labels', 'attachments']);
        } catch (Exception $e) {
            Log::error('Lỗi khi tạo Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể tạo ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    /**
     * Check if a user has workspace-level edit permission for the note's workspace.
     */
    private function hasWorkspaceEditPermission(Note $note, int $userId): bool
    {
        if (!$note->workspace_id) return false;

        $workspace = Workspace::find($note->workspace_id);
        if (!$workspace) return false;

        // Workspace owner has full edit permission over all notes inside
        if ($workspace->user_id === $userId) return true;

        // Check if user has an edit-level workspace share
        return (bool) $workspace->shares()
            ->where('shared_with_user_id', $userId)
            ->where('permission', 'edit')
            ->exists();
    }

    /**
     * Update an existing note. Checks ownership, note-level edit share,
     * or workspace-level edit share.
     */
    public function update(Note $note, array $data, int $updatedByUserId): Note
    {
        try {
            $isOwner = $note->user_id === $updatedByUserId;
            $hasNoteEditPerm = !$isOwner && $note->shares()
                ->where('shared_with_user_id', $updatedByUserId)
                ->where('permission', 'edit')
                ->exists();
            $hasWsEditPerm = !$isOwner && $this->hasWorkspaceEditPermission($note, $updatedByUserId);

            if (!$isOwner && !$hasNoteEditPerm && !$hasWsEditPerm) {
                throw new Exception('Bạn không có quyền chỉnh sửa ghi chú này.');
            }

            $updated = DB::transaction(function () use ($note, $data, $updatedByUserId) {
                if (array_key_exists('title', $data)) {
                    $note->title = $data['title'];
                }
                if (array_key_exists('content', $data)) {
                    $note->content = $data['content'];
                }
                if (array_key_exists('is_pinned', $data)) {
                    $note->is_pinned = $data['is_pinned'];
                }

                $note->save();

                if (array_key_exists('label_ids', $data)) {
                    // Current user's labels already attached to this note
                    $currentUserLabelIds = $note->labels()
                        ->where('labels.user_id', $updatedByUserId)
                        ->pluck('labels.id')
                        ->toArray();

                    $newLabelIds = $data['label_ids'] ?? [];

                    // Detach labels that are no longer selected by this user
                    $toDetach = array_diff($currentUserLabelIds, $newLabelIds);
                    if (!empty($toDetach)) {
                        $note->labels()->detach($toDetach);
                    }

                    // Attach newly selected labels by this user
                    $toAttach = array_diff($newLabelIds, $currentUserLabelIds);
                    if (!empty($toAttach)) {
                        $note->labels()->attach($toAttach);
                    }
                }

                return $note;
            });

            return $updated->load(['labels', 'attachments']);
        } catch (Exception $e) {
            // Re-throw permission/authorization errors with original message
            if (str_contains($e->getMessage(), 'quyền') || str_contains($e->getMessage(), 'permission')) {
                throw $e;
            }
            Log::error('Lỗi khi cập nhật Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể cập nhật ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    /**
     * Delete a note. Owner or workspace edit member may delete.
     */
    public function delete(Note $note, int $requestingUserId): void
    {
        $isOwner      = $note->user_id === $requestingUserId;
        $hasWsEditPerm = !$isOwner && $this->hasWorkspaceEditPermission($note, $requestingUserId);

        if (!$isOwner && !$hasWsEditPerm) {
            throw new Exception('Bạn không có quyền xóa ghi chú này.');
        }

        try {
            $note->delete();
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể xóa ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    public function pin(Note $note): Note
    {
        $note->update([
            'is_pinned'  => true,
            'pinned_at'  => now(),
            'updated_at' => now(),
        ]);

        return $note;
    }

    public function unpin(Note $note): Note
    {
        $note->update([
            'is_pinned'  => false,
            'pinned_at'  => null,
            'updated_at' => now(),
        ]);

        return $note;
    }

    // ──────────────────────────────────────────────
    // Password Lock
    // ──────────────────────────────────────────────

    public function setPassword(Note $note, string $password): void
    {
        $note->update([
            'password_hash' => Hash::make($password),
            'is_locked'     => true,
        ]);
    }

    public function removePassword(Note $note): void
    {
        $note->update([
            'password_hash' => null,
            'is_locked'     => false,
        ]);
    }

    public function verifyPassword(Note $note, string $password): bool
    {
        if (!$note->isPasswordProtected()) {
            return true;
        }

        return Hash::check($password, $note->password_hash);
    }
}