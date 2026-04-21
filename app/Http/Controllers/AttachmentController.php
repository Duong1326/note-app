<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Note;
use App\Services\CloudinaryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        // Authorization: only the note owner may upload
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
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

            return response()->json([
                'success' => true,
                'attachment' => [
                    'id' => $attachment->id,
                    'url' => $attachment->secure_url,
                    'thumbnail_url' => $this->cloudinary->thumbnailUrl($attachment->secure_url, 400),
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
        // Authorization: only the note owner may delete
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không có quyền thực hiện.'], 403);
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
}
