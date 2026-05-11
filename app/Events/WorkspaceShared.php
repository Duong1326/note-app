<?php

namespace App\Events;

use App\Models\WorkspaceShare;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a workspace owner shares a workspace with a new user.
 * Notifies the recipient in real-time so they can see the workspace
 * appear in their sidebar without a page reload.
 */
class WorkspaceShared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkspaceShare $share,
        public User $sharedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->share->shared_with_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->share->loadMissing(['workspace:id,name,description,is_default,is_locked', 'workspace.user:id,name,avatar_url']);

        $workspace = $this->share->workspace;

        return [
            'share_id'       => $this->share->id,
            'permission'     => $this->share->permission,
            'workspace_id'   => $workspace->id,
            'workspace_name' => $workspace->name,
            'workspace'      => array_merge($workspace->toListArray(), [
                'owner' => [
                    'name'       => $workspace->user->name ?? '',
                    'avatar_url' => $workspace->user?->avatarUrl(),
                ],
            ]),
            'shared_by' => [
                'id'         => $this->sharedBy->id,
                'name'       => $this->sharedBy->name,
                'avatar_url' => $this->sharedBy->avatarUrl(),
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.shared';
    }
}
