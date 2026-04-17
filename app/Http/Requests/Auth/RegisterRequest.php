<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:6',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Mật khẩu phải có ít nhất 6 số.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'email.unique' => 'Email này đã được sử dụng.',
        ];
    }
}
