<?php

namespace App\Events;

use App\Models\Note;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the note owner enables or changes the lock password.
 * Broadcasts to all shared users AND workspace members so they
 * are immediately prompted to re-enter the password.
 */
class NoteLocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Note $note,
        public User $lockedBy,
        public string $action = 'enabled', // 'enabled' | 'changed'
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        $notifiedIds = [];

        // 1. Note-level shared users
        $this->note->loadMissing('shares');
        foreach ($this->note->shares as $share) {
            $uid = $share->shared_with_user_id;
            if (!in_array($uid, $notifiedIds)) {
                $notifiedIds[] = $uid;
                $channels[] = new PrivateChannel('user.' . $uid);
            }
        }

        // 2. Workspace-level members (shared workspace)
        if ($this->note->workspace_id) {
            $workspace = $this->note->workspace ?? \App\Models\Workspace::with('shares')->find($this->note->workspace_id);
            if ($workspace) {
                // Workspace owner
                $ownerId = $workspace->user_id;
                if ($ownerId !== $this->lockedBy->id && !in_array($ownerId, $notifiedIds)) {
                    $notifiedIds[] = $ownerId;
                    $channels[] = new PrivateChannel('user.' . $ownerId);
                }
                // Workspace share members
                $workspace->loadMissing('shares');
                foreach ($workspace->shares as $ws) {
                    $uid = $ws->shared_with_user_id;
                    if ($uid !== $this->lockedBy->id && !in_array($uid, $notifiedIds)) {
                        $notifiedIds[] = $uid;
                        $channels[] = new PrivateChannel('user.' . $uid);
                    }
                }
            }
        }

        // 3. Also broadcast on the note presence channel so users on the edit page get it
        $channels[] = new PrivateChannel('note.' . $this->note->id);

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'note_id'    => $this->note->id,
            'note_title' => $this->note->title ?: 'Ghi chú không có tiêu đề',
            'action'     => $this->action,
            'locked_by'  => [
                'id'   => $this->lockedBy->id,
                'name' => $this->lockedBy->name,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.locked';
    }
}
