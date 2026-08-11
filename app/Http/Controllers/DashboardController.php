<?php

namespace App\Http\Controllers;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Models\GameSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the games the signed in account is seated in.
     */
    public function index(Request $request): Response
    {
        $seats = $request->user()->gameSeats()
            ->active()
            ->with('game')
            ->get()
            ->sortBy(fn (GameSeat $seat): string => $seat->game->short_name)
            ->values();

        return Inertia::render('dashboard', [
            'gamemasterGames' => $this->gamesFor($seats, GameRole::Gamemaster),
            'playerGames' => $this->gamesFor($seats, GameRole::Player),
        ]);
    }

    /**
     * Describe the games held under one game role, in short name order.
     *
     * Archived games are included so the dashboard can reveal them without a
     * round trip; the payload flags them so they stay hidden by default.
     *
     * @param  Collection<int, GameSeat>  $seats
     * @return Collection<int, array{id: int, name: string, short_name: string, status: string, status_label: string, is_archived: bool}>
     */
    private function gamesFor(Collection $seats, GameRole $role): Collection
    {
        return $seats
            ->filter(fn (GameSeat $seat): bool => $seat->role === $role)
            ->values()
            ->map(fn (GameSeat $seat): array => [
                'id' => $seat->game->id,
                'name' => $seat->game->name,
                'short_name' => $seat->game->short_name,
                'status' => $seat->game->status->value,
                'status_label' => $seat->game->status->label(),
                'is_archived' => $seat->game->status === GameStatus::Archived,
            ]);
    }
}
