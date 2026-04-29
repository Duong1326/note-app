<?php

namespace App\Events;

use App\Models\NoteShare;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the permission level of a share is changed.
 * Notifies the affected user in real-time.
 */
class SharePermissionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NoteShare $share,
        public User $changedBy,
        public string $oldPermission,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->share->shared_with_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'share_id'       => $this->share->id,
            'note_id'        => $this->share->note_id,
            'note_title'     => $this->share->note->title ?: 'Ghi chú không có tiêu đề',
            'old_permission' => $this->oldPermission,
            'new_permission' => $this->share->permission,
            'changed_by'     => $this->changedBy->name,
        ];
    }

    public function broadcastAs(): string
    {
        return 'share.permission_changed';
    }
}
