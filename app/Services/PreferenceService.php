<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;
use Exception;
use Illuminate\Support\Facades\Log;

class PreferenceService
{
    public function upsert(User $user, array $data): UserPreference
    {
        try {
            $preference = UserPreference::firstOrNew(['user_id' => $user->id]);

            if (isset($data['theme'])) {
                $preference->theme = $data['theme'];
            }

            if (isset($data['font_size'])) {
                $preference->font_size = (int) $data['font_size'];
            }

            if (array_key_exists('note_color', $data)) {
                $preference->note_color = $data['note_color'];
            }

            $preference->save();

            return $preference;

        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật Preferences: ' . $e->getMessage());
            throw new Exception('Không thể cập nhật tùy chọn lúc này. Vui lòng thử lại sau.');
        }
    }

    public function getForUser(User $user): UserPreference
    {
        try {
            return $user->preference ?? new UserPreference([
                'user_id' => $user->id,
                'theme' => 'light',
                'font_size' => 16,
                'note_color' => null,
            ]);

        } catch (Exception $e) {
            Log::error('Lỗi khi tải Preferences: ' . $e->getMessage());
            throw new Exception('Không thể tải tùy chọn lúc này. Vui lòng thử lại sau.');
        }
    }
}
