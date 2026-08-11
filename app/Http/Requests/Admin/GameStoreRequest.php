<?php

namespace App\Http\Requests\Admin;

use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GameStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Game::class)],
            'short_name' => [
                'required',
                'string',
                'max:16',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique(Game::class),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Short names appear in turn reports and file names, so they are stored
     * upper case regardless of how they were typed.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if (is_string($this->input('short_name'))) {
            $this->merge(['short_name' => Str::upper(trim($this->input('short_name')))]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'short_name.regex' => 'The short name may only contain letters, numbers and hyphens.',
        ];
    }
}
