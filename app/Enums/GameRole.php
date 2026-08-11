<?php

namespace App\Enums;

/**
 * The role an account holds in a single game.
 *
 * This is a game concept and carries no application permissions: it is
 * unrelated to App\Enums\UserRole, which decides administrator access.
 */
enum GameRole: string
{
    case Player = 'player';
    case Gamemaster = 'gamemaster';

    /**
     * Get the human readable label for the game role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Player => 'Player',
            self::Gamemaster => 'Gamemaster',
        };
    }
}
