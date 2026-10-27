<?php

namespace App\Http\Requests\Period;

use Illuminate\Foundation\Http\FormRequest;

class SyncEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array'],
            'entries.*.serviceIdentifier' => ['required', 'string', 'distinct'],
            'entries.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
