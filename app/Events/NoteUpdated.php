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
 * Fired when a shared note is updated.
 * Broadcasts to all users who have access to this note
 * so they can refresh the content in real-time.
 */
class NoteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Note $note,
        public User $updatedBy,
    ) {}

    /**
     * Broadcast to the note's presence channel AND to each shared user's
     * private channel (so they see updates even if not on the note page).
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('note.' . $this->note->id),
        ];

        // Also notify each shared user on their personal channel
        foreach ($this->note->shares as $share) {
            $channels[] = new PrivateChannel('user.' . $share->shared_with_user_id);
        }

        // Notify the owner too (if updated by a shared user)
        if ($this->updatedBy->id !== $this->note->user_id) {
            $channels[] = new PrivateChannel('user.' . $this->note->user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        // Strip HTML tags and truncate for an excerpt preview
        $rawContent = strip_tags($this->note->content ?? '');

        return [
            'note_id'    => $this->note->id,
            'note_title' => $this->note->title ?: 'Ghi chú không có tiêu đề',
            'note_content' => $this->note->content ?? '',
            'note_excerpt' => mb_substr($rawContent, 0, 120),
            'updated_by' => [
                'id'         => $this->updatedBy->id,
                'name'       => $this->updatedBy->name,
                'avatar_url' => $this->updatedBy->avatarUrl(),
            ],
            'updated_at' => $this->note->updated_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.updated';
    }
}
