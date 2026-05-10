<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WorkspaceLockController extends Controller
{
    public function __construct(private WorkspaceService $workspaceService) {}

    /**
     * POST /workspaces/{workspace}/lock/verify
     * Verify the workspace password and return unlock token.
     */
    public function verify(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeAccess($request, $workspace);

        if (!$workspace->isPasswordProtected()) {
            // Not locked – set session and allow through
            session(['active_workspace_id' => $workspace->id]);
            return response()->json(['success' => true, 'token' => null]);
        }

        $request->validate(['password' => ['required', 'string']]);

        if (!$this->workspaceService->verifyPassword($workspace, $request->input('password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu không đúng. Vui lòng thử lại.',
            ], 422);
        }

        $token = $this->generateToken($workspace->id);

        // Set active workspace in session so the next page load shows its notes
        session(['active_workspace_id' => $workspace->id]);

        return response()->json(['success' => true, 'token' => $token]);
    }

    /**
     * POST /workspaces/{workspace}/lock/enable
     * Enable password protection on a workspace.
     */
    public function enable(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $request->validate([
            'password'              => ['required', 'string', 'min:4', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        if ($workspace->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace này đã được khoá.',
            ], 422);
        }

        $this->workspaceService->setPassword($workspace, $request->input('password'));

        return response()->json([
            'success' => true,
            'message' => 'Đã khoá workspace thành công.',
        ]);
    }

    /**
     * PUT /workspaces/{workspace}/lock/password
     * Change the lock password.
     */
    public function changePassword(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:4', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        if (!$workspace->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace này chưa được khoá.',
            ], 422);
        }

        if (!$this->workspaceService->verifyPassword($workspace, $request->input('current_password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không đúng.',
            ], 422);
        }

        $this->workspaceService->setPassword($workspace, $request->input('password'));
        $token = $this->generateToken($workspace->id);

        return response()->json([
            'success' => true,
            'message' => 'Đã đổi mật khẩu thành công.',
            'token'   => $token,
        ]);
    }

    /**
     * DELETE /workspaces/{workspace}/lock
     * Remove password protection.
     */
    public function disable(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        if (!$workspace->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace này chưa được khoá.',
            ], 422);
        }

        $password = $request->input('password');
        if (!$password || !$this->workspaceService->verifyPassword($workspace, $password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu không đúng. Vui lòng thử lại.',
            ], 422);
        }

        $this->workspaceService->removePassword($workspace);

        return response()->json([
            'success' => true,
            'message' => 'Đã gỡ khoá workspace thành công.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        abort_if($request->user()->id !== $workspace->user_id, 403, 'Bạn không có quyền thực hiện thao tác này.');
    }

    private function authorizeAccess(Request $request, Workspace $workspace): void
    {
        $userId = $request->user()->id;

        if ($userId === $workspace->user_id) {
            return;
        }

        $hasShare = $workspace->shares()
            ->where('shared_with_user_id', $userId)
            ->exists();

        abort_if(!$hasShare, 403, 'Bạn không có quyền truy cập workspace này.');
    }

    private function generateToken(int $workspaceId): string
    {
        $ttl       = 30 * 60; // 30 minutes
        $expiresAt = now()->timestamp + $ttl;
        $payload   = "ws_{$workspaceId}|{$expiresAt}";
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode("{$payload}|{$signature}");
    }
}
