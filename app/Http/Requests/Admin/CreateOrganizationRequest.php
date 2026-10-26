<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'edrpou' => ['required', 'digits:8', 'unique:organizations,edrpou'],
        ];
    }

    public function attributes(): array
    {
        return [
            'edrpou' => 'код ЄДРПОУ',
        ];
    }
}
