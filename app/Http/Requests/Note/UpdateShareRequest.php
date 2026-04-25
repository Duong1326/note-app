<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'permission' => ['required', 'in:read,edit'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission.required' => 'Vui lòng chọn quyền chia sẻ.',
            'permission.in'       => 'Quyền chia sẻ không hợp lệ.',
        ];
    }
}
