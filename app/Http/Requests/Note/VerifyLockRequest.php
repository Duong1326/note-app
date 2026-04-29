<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth middleware already ensures the user is logged in
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }
}
