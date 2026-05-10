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
 * Fired when a new note is created inside a shared workspace.
 * Broadcasts to every workspace member except the creator,
 * so their dashboards can prepend the card in real-time.
 */
class NoteCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Note $note,
        public User $createdBy,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        // Load workspace + its share list once
        $this->note->loadMissing('workspace.shares');
        $workspace = $this->note->workspace;

        if (!$workspace) return [];

        // Notify the workspace owner (if not the creator)
        if ($workspace->user_id !== $this->createdBy->id) {
            $channels[] = new PrivateChannel('user.' . $workspace->user_id);
        }

        // Notify every shared member (regardless of permission) except the creator
        foreach ($workspace->shares as $share) {
            if ($share->shared_with_user_id !== $this->createdBy->id) {
                $channels[] = new PrivateChannel('user.' . $share->shared_with_user_id);
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $this->note->loadMissing(['labels', 'attachments']);

        return [
            'note'         => $this->note->toCardArray(),
            'workspace_id' => $this->note->workspace_id,
            'created_by'   => [
                'id'         => $this->createdBy->id,
                'name'       => $this->createdBy->name,
                'avatar_url' => $this->createdBy->avatarUrl(),
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.created';
    }
}
