<?php

namespace App\Http\Requests\People;

use App\Domain\People\Enums\PersonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização no controller (PersonPolicy).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'document' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'required', Rule::enum(PersonStatus::class)],
        ];
    }
}
