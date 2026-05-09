<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
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
                $note = $user->notes()->create([
                    'title'     => $data['title'],
                    'content'   => $data['content'] ?? null,
                    'is_pinned' => $data['is_pinned'] ?? false,
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
     * Update an existing note. Checks ownership or edit-share permission.
     */
    public function update(Note $note, array $data, int $updatedByUserId): Note
    {
        try {
            $isOwner = $note->user_id === $updatedByUserId;
            $hasEditPermission = !$isOwner && $note->shares()
                ->where('shared_with_user_id', $updatedByUserId)
                ->where('permission', 'edit')
                ->exists();

            if (!$isOwner && !$hasEditPermission) {
                throw new Exception('Bạn không có quyền chỉnh sửa ghi chú này.');
            }

            $updated = DB::transaction(function () use ($note, $data) {
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
                    $note->labels()->sync($data['label_ids'] ?? []);
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
     * Delete a note. Only the owner may delete.
     */
    public function delete(Note $note, int $requestingUserId): void
    {
        if ($note->user_id !== $requestingUserId) {
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