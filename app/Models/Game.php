<?php

namespace App\Models;

use App\Enums\GameStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A game this application owns the metadata for.
 *
 * The application owns the roster — name, short name, status and who sits in
 * the game. The game engine owns the game state and is not modelled here.
 *
 * @property int $id
 * @property string $name
 * @property string $short_name
 * @property GameStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, GameSeat> $seats
 * @property-read Collection<int, GameSeat> $activeSeats
 */
#[Fillable(['name', 'short_name', 'status'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => GameStatus::Setup->value,
    ];

    /**
     * Get every seat in the game, active or retired.
     *
     * @return HasMany<GameSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(GameSeat::class);
    }

    /**
     * Get the seats that are still being played.
     *
     * @return HasMany<GameSeat, $this>
     */
    public function activeSeats(): HasMany
    {
        return $this->seats()->where('is_active', true);
    }

    /**
     * Scope the query to games that are not archived.
     *
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function unarchived(Builder $query): void
    {
        $query->where('status', '!=', GameStatus::Archived);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
        ];
    }
}
