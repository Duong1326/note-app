<?php

namespace App\Events;

use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a note is shared with a user.
 * Broadcasts to the recipient's private channel so they receive
 * a real-time notification.
 */
class NoteShared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NoteShare $share,
        public Note $note,
        public User $sharedBy,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->share->shared_with_user_id),
        ];
    }

    /**
     * Data to send with the broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'share_id'   => $this->share->id,
            'note_id'    => $this->note->id,
            'note_title' => $this->note->title ?: 'Ghi chú không có tiêu đề',
            'shared_by'  => [
                'id'         => $this->sharedBy->id,
                'name'       => $this->sharedBy->name,
                'avatar_url' => $this->sharedBy->avatarUrl(),
            ],
            'permission' => $this->share->permission,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'note.shared';
    }
}
