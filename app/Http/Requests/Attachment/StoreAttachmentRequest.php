<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership of the note is checked in the controller
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng chọn ảnh.',
            'image.image'    => 'File phải là ảnh.',
            'image.mimes'    => 'Ảnh phải có định dạng: jpeg, jpg, png, gif, webp.',
            'image.max'      => 'Kích thước ảnh không được vượt quá 10MB.',
        ];
    }
}
