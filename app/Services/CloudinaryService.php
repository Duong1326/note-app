<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary(config('services.cloudinary.url'));
    }

    /**
     * Upload an image to Cloudinary.
     *
     * @param  UploadedFile  $file
     * @param  int           $noteId  Used to organise files into per-note folders.
     * @return array{public_id: string, secure_url: string, bytes: int, format: string}
     *
     * @throws Exception
     */
    public function uploadImage(UploadedFile $file, int $noteId): array
    {
        try {
            $uploadApi = new UploadApi($this->cloudinary->configuration);

            $result = $uploadApi->upload($file->getRealPath(), [
                'folder'         => 'notes/' . $noteId,
                'resource_type'  => 'image',
                'transformation' => [
                    ['quality' => 'auto', 'fetch_format' => 'auto'],
                ],
            ]);

            return [
                'public_id'  => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'bytes'      => $result['bytes'] ?? 0,
                'format'     => $result['format'] ?? '',
            ];
        } catch (Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage());
            throw new Exception('Image upload failed. Please try again.');
        }
    }

    /**
     * Upload a user avatar to Cloudinary.
     *
     * @param  UploadedFile  $file
     * @param  int           $userId
     * @return array{public_id: string, secure_url: string}
     *
     * @throws Exception
     */
    public function uploadAvatar(UploadedFile $file, int $userId): array
    {
        try {
            $uploadApi = new UploadApi($this->cloudinary->configuration);

            $result = $uploadApi->upload($file->getRealPath(), [
                'folder'          => 'avatars/' . $userId,
                'resource_type'   => 'image',
                'overwrite'       => true,
                // Nén và crop ngay lúc upload → file lưu trữ nhỏ hơn, URL trả về đã tối ưu
                'transformation'  => [
                    [
                        'width'        => 400,
                        'height'       => 400,
                        'crop'         => 'fill',
                        'gravity'      => 'face',
                        'quality'      => 'auto',
                        'fetch_format' => 'auto',
                    ],
                ],
            ]);

            return [
                'public_id'  => $result['public_id'],
                'secure_url' => $result['secure_url'],
            ];
        } catch (Exception $e) {
            Log::error('Cloudinary avatar upload failed: ' . $e->getMessage());
            throw new Exception('Avatar upload failed. Please try again.');
        }
    }

    /**
     * Delete an image from Cloudinary by its public_id.
     * Failures are logged but do not throw, so the DB record can still be removed.
     */
    public function deleteImage(string $publicId): void
    {
        try {
            $uploadApi = new UploadApi($this->cloudinary->configuration);
            $uploadApi->destroy($publicId, ['resource_type' => 'image']);
        } catch (Exception $e) {
            Log::warning('Cloudinary delete failed for ' . $publicId . ': ' . $e->getMessage());
        }
    }

    /**
     * Build a thumbnail URL by injecting a Cloudinary transformation into the secure URL.
     *
     * @param  string  $secureUrl  Original Cloudinary secure URL.
     * @param  int     $size       Width and height (square crop).
     */
    public function thumbnailUrl(string $secureUrl, int $size = 400): string
    {
        return preg_replace(
            '#/upload/#',
            "/upload/c_fill,w_{$size},h_{$size},q_auto/",
            $secureUrl,
            1
        );
    }
}
