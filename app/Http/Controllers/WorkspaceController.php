<?php

namespace App\Http\Controllers;

use App\Events\WorkspaceDeleted;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaceService) {}

    /**
     * GET /workspaces
     * Return all workspaces for the current user (owned + shared).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure default workspace exists
        $user->ensureDefaultWorkspace();

        $owned = $user->workspaces()
            ->withCount(['notes', 'shares'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $w) => $w->toListArray());

        $shared = $user->sharedWorkspaces()
            ->with([
                'workspace' => fn ($q) => $q->withCount('notes'),
                'workspace.user:id,name,avatar_url',
            ])
            ->get()
            ->map(fn ($s) => [
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
            'workspaces' => $owned,
            'shared'     => $shared,
        ]);
    }

    /**
     * POST /workspaces
     * Create a new workspace.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        try {
            $workspace = $this->workspaceService->create($request->user(), $request->validated());

            // Auto-switch to the newly created workspace
            session(['active_workspace_id' => $workspace->id]);

            return response()->json([
                'success'   => true,
                'workspace' => $workspace->toListArray(),
                'message'   => 'Đã tạo workspace thành công.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PUT /workspaces/{workspace}
     * Update workspace name / description.
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        abort_if($request->user()->id !== $workspace->user_id, 403, 'Bạn không có quyền chỉnh sửa workspace này.');

        try {
            $updated = $this->workspaceService->update($workspace, $request->validated());

            return response()->json([
                'success'   => true,
                'workspace' => $updated->toListArray(),
                'message'   => 'Đã cập nhật workspace thành công.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /workspaces/{workspace}
     * Delete a workspace and all its notes.
     */
    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        // Collect shared user IDs BEFORE the workspace (and its shares) are deleted
        $sharedUserIds = $workspace->shares()->pluck('shared_with_user_id')->toArray();
        $wsId   = $workspace->id;
        $wsName = $workspace->name;

        try {
            $this->workspaceService->delete($workspace, $user->id);

            // Broadcast to all shared members so they redirect to their own workspace
            if (!empty($sharedUserIds)) {
                broadcast(new WorkspaceDeleted($wsId, $wsName, $user, $sharedUserIds));
            }

            // Reset session to the user's default workspace so the next
            // dashboard load never tries to query the now-deleted workspace.
            $defaultWs = $user->workspaces()->where('is_default', true)->first();
            if ($defaultWs) {
                session(['active_workspace_id' => $defaultWs->id]);
            } else {
                session()->forget('active_workspace_id');
            }
            // Also clear the lock-verify token for the deleted workspace
            session()->forget('ws_verified_ts_' . $wsId);

            return response()->json([
                'success'              => true,
                'message'              => 'Đã xóa workspace thành công.',
                'redirect_workspace_id'=> $defaultWs?->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Bạn không có quyền xóa workspace này.' ? 403 : 422);
        }
    }

    /**
     * POST /workspaces/{workspace}/switch
     * Switch to a different workspace (stores in session).
     */
    public function switchTo(Request $request, Workspace $workspace): JsonResponse
    {
        $user = $request->user();

        // Allow switching to owned workspaces or shared workspaces
        $isOwner = $workspace->user_id === $user->id;
        $isShared = $workspace->shares()
            ->where('shared_with_user_id', $user->id)
            ->exists();

        abort_if(!$isOwner && !$isShared, 403, 'Bạn không có quyền truy cập workspace này.');

        // Clear verify token of the workspace being LEFT so the user
        // must re-verify if they switch back to a locked workspace later.
        $prevWsId = session('active_workspace_id');
        if ($prevWsId && $prevWsId !== $workspace->id) {
            session()->forget('ws_verified_ts_' . $prevWsId);
        }

        // Store active workspace in session
        session(['active_workspace_id' => $workspace->id]);

        return response()->json([
            'success'   => true,
            'workspace' => $workspace->toListArray(),
            'message'   => 'Đã chuyển sang workspace "' . $workspace->name . '".',
        ]);
    }
}
