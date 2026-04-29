<?php

namespace App\Http\Middleware;

use App\Models\Note;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckNoteToken
{
    /**
     * Validates the X-Note-Token header for locked notes.
     * This middleware is a no-op for notes that are not password-protected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve the note from the route parameter (supports both 'note' and the resource binding)
        $note = $request->route('note');

        // If the route doesn't bind a Note, or the note isn't locked, skip validation
        if (!$note instanceof Note || !$note->isPasswordProtected()) {
            return $next($request);
        }

        $rawToken = $request->header('X-Note-Token');

        if (!$rawToken || !$this->validateToken($rawToken, $note->id)) {
            return response()->json([
                'success'          => false,
                'message'          => 'Ghi chú này đã bị khoá. Vui lòng nhập mật khẩu để tiếp tục.',
                'requires_unlock'  => true,
                'note_id'          => $note->id,
            ], 401);
        }

        return $next($request);
    }

    private function validateToken(string $rawToken, int $noteId): bool
    {
        $decoded = base64_decode($rawToken, strict: true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        [$tokenNoteId, $expiresAt, $signature] = $parts;

        // Check note ID matches
        if ((int) $tokenNoteId !== $noteId) {
            return false;
        }

        // Check token has not expired
        if (now()->timestamp > (int) $expiresAt) {
            return false;
        }

        // Verify HMAC signature
        $payload           = "{$tokenNoteId}|{$expiresAt}";
        $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));

        return hash_equals($expectedSignature, $signature);
    }
}
