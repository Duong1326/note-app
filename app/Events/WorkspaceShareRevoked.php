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
 * Fired when the owner revokes a user's access to a workspace.
 * Notifies the affected user so their sidebar updates immediately.
 */
class WorkspaceShareRevoked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $workspaceId,
        public string $workspaceName,
        public int $revokedUserId,
        public User $revokedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->revokedUserId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id'   => $this->workspaceId,
            'workspace_name' => $this->workspaceName,
            'revoked_by' => [
                'id'   => $this->revokedBy->id,
                'name' => $this->revokedBy->name,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.share_revoked';
    }
}
