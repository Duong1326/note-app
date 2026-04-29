<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a note is deleted by its owner.
 * Broadcasts to every user who had access so they can remove
 * the card from their shared-notes section in real-time.
 */
class NoteDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $noteId,
        public string $noteTitle,
        public User $deletedBy,
        public array $sharedUserIds,
    ) {
    }

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
            'note_id' => $this->noteId,
            'note_title' => $this->noteTitle,
            'deleted_by' => [
                'id' => $this->deletedBy->id,
                'name' => $this->deletedBy->name,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.deleted';
    }
}
