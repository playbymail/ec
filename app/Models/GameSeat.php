<?php

namespace App\Models;

use App\Enums\GameRole;
use Carbon\CarbonImmutable;
use Database\Factories\GameSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account's seat in one game.
 *
 * An account holds at most one seat per game, enforced by a unique index. A
 * seat that is no longer played is retired by clearing is_active rather than
 * deleted, because the engine's history keeps referring to it — so putting a
 * departed account back in the game means reactivating its seat.
 *
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property GameRole $role
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Game $game
 * @property-read User $user
 */
#[Fillable(['user_id', 'role', 'is_active'])]
class GameSeat extends Model
{
    /** @use HasFactory<GameSeatFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => GameRole::Player->value,
        'is_active' => true,
    ];

    /**
     * Get the game the seat belongs to.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the account sitting in the seat.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the seat is held by a gamemaster.
     */
    public function isGamemaster(): bool
    {
        return $this->role === GameRole::Gamemaster;
    }

    /**
     * Scope the query to seats that are still being played.
     *
     * @param  Builder<GameSeat>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => GameRole::class,
            'is_active' => 'boolean',
        ];
    }
}
