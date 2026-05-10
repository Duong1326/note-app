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
     * Also broadcasts to all workspace members when the note belongs to a workspace.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('note.' . $this->note->id),
        ];

        // Collect unique user IDs to notify
        $notifiedIds = [];

        // 1. Note-level shared users
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
                if ($ownerId !== $this->updatedBy->id && !in_array($ownerId, $notifiedIds)) {
                    $notifiedIds[] = $ownerId;
                    $channels[] = new PrivateChannel('user.' . $ownerId);
                }
                // Workspace share members
                foreach ($workspace->shares as $ws) {
                    $uid = $ws->shared_with_user_id;
                    if ($uid !== $this->updatedBy->id && !in_array($uid, $notifiedIds)) {
                        $notifiedIds[] = $uid;
                        $channels[] = new PrivateChannel('user.' . $uid);
                    }
                }
            }
        }

        // 3. Notify the note owner (if updated by someone else and not yet added)
        if ($this->updatedBy->id !== $this->note->user_id && !in_array($this->note->user_id, $notifiedIds)) {
            $channels[] = new PrivateChannel('user.' . $this->note->user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        // Strip HTML tags and truncate for an excerpt preview
        $rawContent = strip_tags($this->note->content ?? '');

        // Map attachments so the client can update thumbnails on note cards
        $attachments = $this->note->attachments->map(fn($a) => [
            'id'            => $a->id,
            'url'           => $a->secure_url,
            'thumbnail_url' => $a->thumbnailUrl(400),
        ])->values()->all();

        return [
            'note_id'      => $this->note->id,
            'workspace_id' => $this->note->workspace_id,
            'note_title'   => $this->note->title ?: 'Ghi chú không có tiêu đề',
            'note_content' => $this->note->content ?? '',
            'note_excerpt' => mb_substr($rawContent, 0, 120),
            'attachments'  => $attachments,
            'updated_by'   => [
                'id'         => $this->updatedBy->id,
                'name'       => $this->updatedBy->name,
                'avatar_url' => $this->updatedBy->avatarUrl(),
            ],
            'updated_at'   => $this->note->updated_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.updated';
    }
}
