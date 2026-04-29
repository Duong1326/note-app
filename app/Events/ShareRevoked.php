<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user's access to a shared note is revoked.
 * Notifies the affected user so they can remove the note from their UI.
 */
class ShareRevoked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sharedWithUserId,
        public int $noteId,
        public string $noteTitle,
        public User $revokedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->sharedWithUserId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'note_id'    => $this->noteId,
            'note_title' => $this->noteTitle ?: 'Ghi chú không có tiêu đề',
            'revoked_by' => $this->revokedBy->name,
        ];
    }

    public function broadcastAs(): string
    {
        return 'share.revoked';
    }
}
