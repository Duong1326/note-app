<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WorkspaceService
{
    /**
     * Create a new workspace for the given user.
     */
    public function create(User $user, array $data): Workspace
    {
        try {
            return $user->workspaces()->create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi tạo Workspace: ' . $e->getMessage());
            throw new Exception('Không thể tạo workspace lúc này. Vui lòng thử lại sau.');
        }
    }

    /**
     * Update an existing workspace.
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        if ($workspace->is_default && isset($data['name']) && $data['name'] !== $workspace->name) {
            throw new Exception('Không thể đổi tên workspace mặc định.');
        }

        try {
            $workspace->update(array_filter([
                'name'        => $data['name'] ?? $workspace->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $workspace->description,
            ], fn ($v) => $v !== null));

            return $workspace->fresh();
        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật Workspace: ' . $e->getMessage());
            throw new Exception('Không thể cập nhật workspace lúc này. Vui lòng thử lại sau.');
        }
    }

    /**
     * Delete a workspace and ALL notes inside it.
     */
    public function delete(Workspace $workspace, int $requestingUserId): void
    {
        if ($workspace->user_id !== $requestingUserId) {
            throw new Exception('Bạn không có quyền xóa workspace này.');
        }

        if ($workspace->is_default) {
            throw new Exception('Không thể xóa workspace mặc định.');
        }

        try {
            DB::transaction(function () use ($workspace) {
                // Delete all notes inside (including their attachments, shares, etc. via cascade)
                $workspace->notes()->delete();
                // Delete workspace shares
                $workspace->shares()->delete();
                // Delete the workspace itself
                $workspace->delete();
            });
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa Workspace: ' . $e->getMessage());
            throw new Exception('Không thể xóa workspace lúc này. Vui lòng thử lại sau.');
        }
    }

    // ──────────────────────────────────────────────
    // Password Lock
    // ──────────────────────────────────────────────

    public function setPassword(Workspace $workspace, string $password): void
    {
        $workspace->update([
            'password_hash' => Hash::make($password),
            'is_locked'     => true,
        ]);
    }

    public function removePassword(Workspace $workspace): void
    {
        $workspace->update([
            'password_hash' => null,
            'is_locked'     => false,
        ]);
    }

    public function verifyPassword(Workspace $workspace, string $password): bool
    {
        if (!$workspace->isPasswordProtected()) {
            return true;
        }

        return Hash::check($password, $workspace->password_hash);
    }
}
