<?php

namespace App\Http\Requests\Jurors;

use App\Domain\Jurors\Enums\JurorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeJurorStatusRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('juror'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(JurorStatus::class)],
        ];
    }
}
