<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class ShareNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'emails'     => ['required', 'array', 'min:1', 'max:10'],
            'emails.*'   => ['required', 'email', 'exists:users,email'],
            'permission' => ['required', 'in:read,edit'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required'   => 'Vui lòng nhập ít nhất một địa chỉ email.',
            'emails.max'        => 'Tối đa 10 người nhận mỗi lần chia sẻ.',
            'emails.*.email'    => 'Địa chỉ email không hợp lệ.',
            'emails.*.exists'   => 'Tài khoản email ":input" không tồn tại trong hệ thống.',
            'permission.required' => 'Vui lòng chọn quyền chia sẻ.',
            'permission.in'     => 'Quyền chia sẻ không hợp lệ.',
        ];
    }
}
