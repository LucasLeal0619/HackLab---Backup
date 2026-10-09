<?php

namespace App\Http\Requests\Challenges;

use App\Domain\Challenges\Enums\ChallengeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeChallengeStatusRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('challenge'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ChallengeStatus::class)],
        ];
    }
}
