<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Enums\RoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização no controller (UserPolicy).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RoleCode::class)],
        ];
    }
}
