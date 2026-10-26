<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'edrpou' => [
                'required', 'digits:8',
                Rule::unique('organizations', 'edrpou')->ignore($this->route('identifier'), 'identifier'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'edrpou' => 'код ЄДРПОУ',
        ];
    }
}
