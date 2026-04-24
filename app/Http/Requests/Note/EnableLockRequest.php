<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class EnableLockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');
        return $note && $this->user()?->id === $note->user_id;
    }

    public function rules(): array
    {
        return [
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required'  => 'Vui lòng nhập mật khẩu.',
            'password.min'       => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ];
    }
}
