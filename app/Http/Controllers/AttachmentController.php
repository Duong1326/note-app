<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Note;
use App\Services\CloudinaryService;
use App\Events\NoteUpdated;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttachmentController extends Controller
{
    public function __construct(private CloudinaryService $cloudinary)
    {
    }

    /**
     * Upload an image to Cloudinary and persist the record.
     * POST /notes/{note}/attachments
     */
    public function store(StoreAttachmentRequest $request, Note $note): JsonResponse
    {
        // Authorization: owner OR shared user with edit permission may upload
        if (!$this->canEdit($request, $note)) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền tải ảnh lên ghi chú này.'], 403);
        }

        try {
            $file = $request->file('image');
            $result = $this->cloudinary->uploadImage($file, $note->id);

            $attachment = $note->attachments()->create([
                'cloudinary_public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'mime_type' => $file->getMimeType(),
                'size' => $result['bytes'],
            ]);

            $thumbnailUrl = $this->cloudinary->thumbnailUrl($attachment->secure_url, 400);

            // Broadcast real-time update AFTER response is sent (non-blocking)
            $user = $request->user();
            app()->terminating(function () use ($note, $user) {
                try {
                    $note->load(['attachments', 'shares']);
                    NoteUpdated::dispatch($note, $user);
                } catch (Exception $e) {
                    Log::warning('Broadcast after attachment upload failed: ' . $e->getMessage());
                }
            });

            return response()->json([
                'success'    => true,
                'attachment' => [
                    'id'            => $attachment->id,
                    'url'           => $attachment->secure_url,
                    'thumbnail_url' => $thumbnailUrl,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an image from Cloudinary and remove the DB record.
     * DELETE /notes/{note}/attachments/{attachment}
     */
    public function destroy(Request $request, Note $note, Attachment $attachment): JsonResponse
    {
        // Authorization: owner OR shared user with edit permission may delete
        if (!$this->canEdit($request, $note)) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền xóa ảnh của ghi chú này.'], 403);
        }

        // Ensure the attachment belongs to this note
        if ($attachment->note_id !== $note->id) {
            return response()->json(['success' => false, 'message' => 'Ảnh không thuộc ghi chú này.'], 404);
        }

        try {
            $this->cloudinary->deleteImage($attachment->cloudinary_public_id);
            $attachment->delete();

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Returns true if the authenticated user is the note owner
     * OR has been granted the 'edit' share permission.
     */
    private function canEdit(\Illuminate\Http\Request $request, Note $note): bool
    {
        $userId = $request->user()->id;

        if ($note->user_id === $userId) {
            return true;
        }

        return $note->shares()
            ->where('shared_with_user_id', $userId)
            ->where('permission', 'edit')
            ->exists();
    }
}
