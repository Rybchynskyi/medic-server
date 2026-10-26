<?php

namespace App\Http\Requests\Auth;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'edrpou' => ['required', 'digits:8'],
            // Only needed when this EDRPOU isn't registered yet; otherwise the user joins the existing organization.
            'organizationName' => [
                Rule::requiredIf(fn () => ! Organization::where('edrpou', $this->input('edrpou'))->exists()),
                'nullable', 'string', 'max:255',
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ім\'я',
            'edrpou' => 'код ЄДРПОУ',
            'organizationName' => 'назва установи',
            'email' => 'email',
            'password' => 'пароль',
        ];
    }
}
