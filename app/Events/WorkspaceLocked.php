<?php

namespace App\Events;

use App\Models\Workspace;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a workspace owner enables or changes the lock password.
 * Broadcasts to every member currently in that workspace so they
 * are immediately prompted to re-enter the new password.
 */
class WorkspaceLocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public User $lockedBy,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        $this->workspace->loadMissing('shares');

        // Notify all shared members (owner triggers this, so notify members)
        foreach ($this->workspace->shares as $share) {
            $channels[] = new PrivateChannel('user.' . $share->shared_with_user_id);
        }

        // Also notify the owner themselves on other sessions/tabs
        $channels[] = new PrivateChannel('user.' . $this->workspace->user_id);

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id'   => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'locked_by'      => $this->lockedBy->id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.locked';
    }
}
