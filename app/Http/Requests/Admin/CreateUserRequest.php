<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            // Empty means "send an invitation to set their own password".
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(RoleNames::getValues())],
            'organizationIdentifier' => ['nullable', 'string', 'exists:organizations,identifier'],
        ];
    }
}
