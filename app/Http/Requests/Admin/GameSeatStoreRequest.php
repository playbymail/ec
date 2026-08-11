<?php

namespace App\Http\Requests\Admin;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\GameSeat;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameSeatStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $game = $this->route('game');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique(GameSeat::class, 'user_id')->where(
                    fn (Builder $query) => $query->where(
                        'game_id',
                        $game instanceof Game ? $game->id : $game,
                    ),
                ),
            ],
            'role' => ['required', Rule::enum(GameRole::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * The unique rule counts retired seats too, since the seat still exists —
     * reactivate it instead of adding a second one.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'That account already has a seat in this game.',
        ];
    }
}
