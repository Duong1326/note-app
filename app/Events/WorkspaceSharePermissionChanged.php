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
 * Fired when the owner changes the permission of a workspace share.
 * Notifies the affected shared user in real-time so their UI can update
 * (e.g. show/hide the "New Note" button, update sidebar permission badge).
 */
class WorkspaceSharePermissionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkspaceShare $share,
        public User $changedBy,
        public string $oldPermission,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->share->shared_with_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->share->loadMissing('workspace:id,name');

        return [
            'workspace_id'   => $this->share->workspace_id,
            'workspace_name' => $this->share->workspace->name ?? 'Workspace',
            'old_permission' => $this->oldPermission,
            'new_permission' => $this->share->permission,
            'changed_by'     => $this->changedBy->name,
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.share_permission_changed';
    }
}
