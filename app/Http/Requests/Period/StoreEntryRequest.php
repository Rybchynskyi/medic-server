<?php

namespace App\Http\Requests\Period;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serviceIdentifier' => ['required', 'string', 'exists:services,identifier'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
