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
            ...self::clientDetailRules(),
        ];
    }

    public function attributes(): array
    {
        return self::clientDetailAttributes();
    }

    /**
     * Optional client-base fields, shared with CreateOrganizationRequest.
     */
    public static function clientDetailRules(): array
    {
        return [
            'ourName' => ['nullable', 'string', 'max:500'],
            'director' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'contactPerson' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:100'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contracts' => ['nullable', 'string', 'max:2000'],
            'adminNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function clientDetailAttributes(): array
    {
        return [
            'edrpou' => 'код ЄДРПОУ',
            'contactEmail' => 'email контактної особи',
        ];
    }
}
