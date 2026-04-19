<?php

namespace App\Http\Requests\Preference;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['sometimes', Rule::in(['light', 'dark'])],
            'font_size' => ['sometimes', 'integer', 'min:10', 'max:32'],
            'note_color' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}