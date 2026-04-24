<?php

namespace App\Http\Controllers;

use App\Http\Requests\Note\ChangeLockPasswordRequest;
use App\Http\Requests\Note\EnableLockRequest;
use App\Http\Requests\Note\VerifyLockRequest;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NoteLockController extends Controller
{
    public function __construct(private NoteService $noteService) {}

    /**
     * POST /notes/{note}/lock/verify
     * Verify the note password and return a short-lived HMAC unlock token.
     */
    public function verify(VerifyLockRequest $request, Note $note): JsonResponse
    {
        $this->authorizeNoteOwner($request, $note);

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
