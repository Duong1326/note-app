<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a workspace owner deletes their workspace.
 * Broadcasts to all shared members so they are immediately
 * redirected to their personal workspace.
 */
class WorkspaceDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<int> $sharedUserIds List of user IDs who had access to this workspace */
    public function __construct(
        public int $workspaceId,
        public string $workspaceName,
        public User $deletedBy,
        public array $sharedUserIds,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->sharedUserIds as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }
        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id'   => $this->workspaceId,
            'workspace_name' => $this->workspaceName,
            'deleted_by' => [
                'id'   => $this->deletedBy->id,
                'name' => $this->deletedBy->name,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.deleted';
    }
}
