<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Note;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private channel for each user — receives personal notifications
 * (e.g. "User X shared a note with you").
 */
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel for collaborative note editing.
 * Only the note owner or users who have been granted access can join.
 * Returns user info so we can display "who is viewing this note".
 */
Broadcast::channel('note.{noteId}', function ($user, $noteId) {
    $note = Note::find($noteId);
    if (!$note) {
        return false;
    }

    // Owner can always join
    if ($note->user_id === $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
        ];
    }

    // Users who have been shared with can join
    $share = $note->shareFor($user->id);
    if ($share) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
            'permission' => $share->permission,
        ];
    }

    return false;
});
