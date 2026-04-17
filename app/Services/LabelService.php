<?php

namespace App\Services;

use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

class LabelService
{
    public function listForUser(User $user): Collection
    {
        try {
            return $user->labels()->orderBy('name')->get();
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy danh sách nhãn: ' . $e->getMessage());
            throw new Exception('Không thể tải danh sách nhãn. Vui lòng thử lại sau.');
        }
    }

    public function create(User $user, string $name): Label
    {
        try {
            $exist = $user->labels()->where('name', $name)->exists();

            if ($exist) {
                throw ValidationException::withMessages([
                    'name' => ["Tên nhãn không được trùng"],
                ]);
            }

            return $user->labels()->create([
                'name' => $name,
            ]);
        } catch (ValidationException $e) {
            throw $e; // Giữ nguyên lỗi validation để Controller/Laravel tự xử lý
        } catch (Exception $e) {
            Log::error('Lỗi khi tạo nhãn mới: ' . $e->getMessage());
            throw new Exception('Có lỗi xảy ra khi hệ thống tạo nhãn: ' . $e->getMessage());
        }
    }

    public function rename(Label $label, string $newName): Label
    {
        try {
            $exist = $label->where('user_id', $label->user_id)
                ->where('name', $newName)
                ->where('id', '!=', $label->id)
                ->exists();

            if ($exist) {
                throw ValidationException::withMessages([
                    'name' => ["Tên nhãn không được trùng"],
                ]);
            }

            $label->update([
                'name' => $newName,
            ]);

            return $label;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật tên nhãn: ' . $e->getMessage());
            throw new Exception('Có lỗi xảy ra khi đổi tên nhãn: ' . $e->getMessage());
        }
    }

    public function delete(Label $label): void
    {
        try {
            $label->delete();
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa nhãn: ' . $e->getMessage());
            throw new Exception('Có lỗi xảy ra khi xóa nhãn: ' . $e->getMessage());
        }
    }

}