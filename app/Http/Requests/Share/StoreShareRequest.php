<?php

namespace App\Http\Requests\Share;

use App\Models\Share;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'permission' => ['required', Rule::in([Share::PERMISSION_READ, Share::PERMISSION_EDIT])],
        ];
    }
}