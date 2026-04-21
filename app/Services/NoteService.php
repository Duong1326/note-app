<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NoteService
{
    private const PER_PAGE = 20;

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = Note::ownedBy($user->id)
                ->with(['labels', 'attachments'])
                ->defaultOrder();

            if (!empty($filters['q'])) {
                $keyword = $filters['q'];
                if (DB::getDriverName() === 'mysql') {
                    $query->whereRaw(
                        'MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)',
                        [$keyword]
                    );
                } else {
                    $query->search($keyword);
                }
            }

            if (!empty($filters['label_ids'])) {
                $query->withLabels($filters['label_ids']);
            }

            return $query->paginate(self::PER_PAGE);

        } catch (Exception $e) {
            Log::error('Lỗi khi tải danh sách Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể tải danh sách ghi chú của bạn lúc này. Vui lòng thử lại sau.');
        }
    }

    public function create(User $user, array $data): Note
    {
        try {
            $note = DB::transaction(function () use ($user, $data) {
                $note = $user->notes()->create([
                    'title' => $data['title'],
                    'content' => $data['content'] ?? null,
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

    public function delete(Note $note): void
    {
        try {
            $note->delete();
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể xóa ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    public function listSharedWithUser(User $user): LengthAwarePaginator
    {
        try {
            $query = Note::query()
                ->whereHas('shares', function (Builder $q) use ($user) {
                    $q->where('shared_with_user_id', $user->id);
                })
                ->with(['user', 'labels'])
                ->defaultOrder()
                ->paginate(self::PER_PAGE);

            return $query;

        } catch (Exception $e) {
            Log::error('Lỗi khi tải danh sách Ghi chú được chia sẻ: ' . $e->getMessage());
            throw new Exception('Không thể tải danh sách ghi chú được chia sẻ lúc này. Vui lòng thử lại sau.');
        }
    }

    public function update(
        Note $note,
        array $data,
        int $updatedByUserId
    ): Note {
        try {
            // Kiểm tra quyền: chỉ chủ sở hữu mới được cập nhật
            if ($note->user_id !== $updatedByUserId) {
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
            Log::error('Lỗi khi cập nhật Ghi chú: ' . $e->getMessage());
            throw new Exception('Không thể cập nhật ghi chú lúc này. Vui lòng thử lại sau.');
        }
    }

    public function pin(Note $note): Note
    {
        $note->update([
            'is_pinned' => true,
            'pinned_at' => now(),
        ]);

        return $note;
    }

    public function unpin(Note $note): Note
    {
        $note->update([
            'is_pinned' => false,
            'pinned_at' => null,
        ]);

        return $note;
    }
}