<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workspaces', 'name')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên workspace.',
            'name.max'      => 'Tên workspace tối đa 255 ký tự.',
            'name.unique'   => 'Tên workspace này đã tồn tại, vui lòng chọn tên khác.',
        ];
    }
}
