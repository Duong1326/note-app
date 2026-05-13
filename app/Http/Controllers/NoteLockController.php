<?php

namespace App\Http\Controllers;

use App\Events\NoteLocked;
use App\Http\Requests\Note\ChangeLockPasswordRequest;
use App\Http\Requests\Note\EnableLockRequest;
use App\Http\Requests\Note\VerifyLockRequest;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteLockController extends Controller
{
    public function __construct(private NoteService $noteService) {}

    /**
     * POST /notes/{note}/lock/verify
     * Verify the note password and return a short-lived HMAC unlock token.
     */
    public function verify(VerifyLockRequest $request, Note $note): JsonResponse
    {
        // Both the owner AND shared users with 'edit' permission may verify the password.
        // (Shared read-only users cannot write, so they never need a token.)
        $this->authorizeNoteAccess($request, $note);

        if (!$note->isPasswordProtected()) {
            return response()->json(['success' => true, 'token' => null]);
        }

        if (!$this->noteService->verifyPassword($note, $request->input('password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu không đúng. Vui lòng thử lại.',
            ], 422);
        }

        $token = $this->generateToken($note->id);

        return response()->json(['success' => true, 'token' => $token]);
    }

    /**
     * POST /notes/{note}/lock/enable
     * Enable password protection on a note.
     */
    public function enable(EnableLockRequest $request, Note $note): JsonResponse
    {
        $this->authorizeNoteOwner($request, $note);

        if ($note->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Ghi chú này đã được khoá.',
            ], 422);
        }

        $this->noteService->setPassword($note, $request->input('password'));

        // Broadcast to shared users so they are immediately prompted for the password
        broadcast(new NoteLocked($note->fresh(), $request->user(), 'enabled'))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Đã khoá ghi chú thành công.',
        ]);
    }

    /**
     * PUT /notes/{note}/lock/password
     * Change the lock password (requires current password).
     */
    public function changePassword(ChangeLockPasswordRequest $request, Note $note): JsonResponse
    {
        $this->authorizeNoteOwner($request, $note);

        if (!$note->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Ghi chú này chưa được khoá.',
            ], 422);
        }

        if (!$this->noteService->verifyPassword($note, $request->input('current_password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không đúng.',
            ], 422);
        }

        $this->noteService->setPassword($note, $request->input('password'));

        // Broadcast to shared users so they are immediately prompted for the new password
        broadcast(new NoteLocked($note->fresh(), $request->user(), 'changed'))->toOthers();

        // Return a fresh token so the user stays unlocked after changing password
        $token = $this->generateToken($note->id);

        return response()->json([
            'success' => true,
            'message' => 'Đã đổi mật khẩu thành công.',
            'token'   => $token,
        ]);
    }

    /**
     * DELETE /notes/{note}/lock
     * Remove password protection (requires current password).
     */
    public function disable(Request $request, Note $note): JsonResponse
    {
        $this->authorizeNoteOwner($request, $note);

        if (!$note->isPasswordProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'Ghi chú này chưa được khoá.',
            ], 422);
        }

        $password = $request->input('password');
        if (!$password || !$this->noteService->verifyPassword($note, $password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu không đúng. Vui lòng thử lại.',
            ], 422);
        }

        $this->noteService->removePassword($note);

        return response()->json([
            'success' => true,
            'message' => 'Đã gỡ khoá ghi chú thành công.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function authorizeNoteOwner(Request $request, Note $note): void
    {
        abort_if($request->user()->id !== $note->user_id, 403, 'Bạn không có quyền thực hiện thao tác này.');
    }

    /**
     * Allow both the note owner and shared users with 'edit' permission.
     * Used for verify() so shared editors can unlock the note.
     */
    private function authorizeNoteAccess(Request $request, Note $note): void
    {
        $userId = $request->user()->id;

        if ($userId === $note->user_id) {
            return; // owner — always allowed
        }

        $hasEditShare = $note->shares()
            ->where('shared_with_user_id', $userId)
            ->where('permission', 'edit')
            ->exists();

        abort_if(!$hasEditShare, 403, 'Bạn không có quyền thực hiện thao tác này.');
    }

    /**
     * Generate a signed HMAC token encoding the note ID and an expiry timestamp.
     * Format: base64(noteId . '|' . expiresAt . '|' . hmac)
     */
    private function generateToken(int $noteId): string
    {
        $ttl       = 15 * 60; // 15 minutes
        $expiresAt = now()->timestamp + $ttl;
        $payload   = "{$noteId}|{$expiresAt}";
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode("{$payload}|{$signature}");
    }
}
