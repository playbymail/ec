<?php

namespace App\Http\Requests\Admin;

use App\Enums\GameRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameSeatUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(GameRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
