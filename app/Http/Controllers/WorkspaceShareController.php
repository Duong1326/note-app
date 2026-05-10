<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\ShareWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceShareController extends Controller
{
    /**
     * GET /workspaces/{workspace}/shares
     * Return all share records for this workspace (owner only).
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $shares = $workspace->shares()->with('sharedWith:id,name,email,avatar_url')->get()
            ->map(fn (WorkspaceShare $s) => [
                'id'         => $s->id,
                'user_id'    => $s->shared_with_user_id,
                'name'       => $s->sharedWith->name,
                'email'      => $s->sharedWith->email,
                'avatar_url' => $s->sharedWith->avatarUrl(),
                'permission' => $s->permission,
                'shared_at'  => $s->created_at->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'shares' => $shares]);
    }

    /**
     * POST /workspaces/{workspace}/shares
     * Share a workspace with one or more email addresses.
     */
    public function store(ShareWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $permission = $request->validated('permission');
        $emails     = $request->validated('emails');
        $created    = [];
        $skipped    = [];

        foreach ($emails as $email) {
            $recipient = User::where('email', $email)->first();

            if ($recipient->id === $request->user()->id) {
                $skipped[] = $email;
                continue;
            }

            $share = WorkspaceShare::updateOrCreate(
                [
                    'workspace_id'         => $workspace->id,
                    'shared_with_user_id'  => $recipient->id,
                ],
                [
                    'owner_id'   => $workspace->user_id,
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
                'shared_at'  => $share->created_at->diffForHumans(),
            ];
        }

        return response()->json([
            'success' => true,
            'shares'  => $created,
            'skipped' => $skipped,
            'message' => count($created) > 0
                ? 'Đã chia sẻ workspace thành công.'
                : 'Không có người dùng nào được thêm mới.',
        ]);
    }

    /**
     * PUT /workspaces/{workspace}/shares/{share}
     * Update the permission level of an existing share.
     */
    public function update(Request $request, Workspace $workspace, WorkspaceShare $share): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        abort_if($share->workspace_id !== $workspace->id, 404);

        $request->validate(['permission' => ['required', 'in:read,edit']]);

        $share->update(['permission' => $request->input('permission')]);

        return response()->json([
            'success'    => true,
            'permission' => $share->permission,
            'message'    => 'Đã cập nhật quyền chia sẻ.',
        ]);
    }

    /**
     * DELETE /workspaces/{workspace}/shares/{share}
     * Revoke a single user's access.
     */
    public function destroy(Request $request, Workspace $workspace, WorkspaceShare $share): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        abort_if($share->workspace_id !== $workspace->id, 404);

        $share->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã thu hồi quyền truy cập.',
        ]);
    }

    /**
     * GET /shared-workspaces
     * Return workspaces shared with the current user.
     */
    public function sharedWithMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $shared = $user->sharedWorkspaces()
            ->with([
                'workspace' => fn ($q) => $q->withCount('notes'),
                'workspace.user:id,name,avatar_url',
            ])
            ->latest()
            ->get()
            ->map(fn (WorkspaceShare $s) => [
                'share_id'   => $s->id,
                'permission' => $s->permission,
                'workspace'  => array_merge($s->workspace->toListArray(), [
                    'owner' => [
                        'name'       => $s->workspace->user->name ?? '',
                        'avatar_url' => $s->workspace->user?->avatarUrl(),
                    ],
                ]),
            ]);

        return response()->json([
            'success'    => true,
            'workspaces' => $shared,
        ]);
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        abort_if(
            $request->user()->id !== $workspace->user_id,
            403,
            'Bạn không có quyền quản lý chia sẻ của workspace này.'
        );
    }
}
