<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Share;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShareService
{
    public function share(Note $note, User $owner, string $recipientEmail, string $permission): Share
    {
        try {
            $recipient = User::where('email', $recipientEmail)->first();

            if (!$recipient) {
                throw ValidationException::withMessages([
                    'email' => ['Không tìm thấy người dùng với email này.'],
                ]);
            }

            if ($recipient->id === $owner->id) {
                throw ValidationException::withMessages([
                    'email' => ['Bạn không thể chia sẻ ghi chú với chính mình.'],
                ]);
            }

            if ($note->user_id !== $owner->id) {
                throw new Exception('Bạn không có quyền chia sẻ ghi chú này.');
            }

            return DB::transaction(function () use ($note, $owner, $recipient, $permission) {
                $existing = Share::where('note_id', $note->id)
                    ->where('shared_with_user_id', $recipient->id)
                    ->first();

                if ($existing) {
                    $existing->update(['permission' => $permission]);
                    return $existing->fresh();
                }

                return Share::create([
                    'note_id' => $note->id,
                    'owner_id' => $owner->id,
                    'shared_with_user_id' => $recipient->id,
                    'permission' => $permission,
                ]);
            });

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Lỗi khi chia sẻ Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể chia sẻ ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    public function updatePermission(Share $share, string $permission): Share
    {
        try {
            $share->update(['permission' => $permission]);
            return $share->fresh();

        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật quyền chia sẻ: ' . $e->getMessage());
            throw new Exception('Không thể cập nhật quyền chia sẻ lúc này. Vui lòng thử lại sau.');
        }
    }

    public function revoke(Share $share): void
    {
        try {
            $share->delete();

        } catch (Exception $e) {
            Log::error('Lỗi khi thu hồi chia sẻ: ' . $e->getMessage());
            throw new Exception('Không thể thu hồi chia sẻ lúc này. Vui lòng thử lại sau.');
        }
    }

    public function listForNote(Note $note): Collection
    {
        try {
            return $note->shares()->with('recipient')->get();

        } catch (Exception $e) {
            Log::error('Lỗi khi tải danh sách chia sẻ: ' . $e->getMessage());
            throw new Exception('Không thể tải danh sách chia sẻ lúc này. Vui lòng thử lại sau.');
        }
    }
}
