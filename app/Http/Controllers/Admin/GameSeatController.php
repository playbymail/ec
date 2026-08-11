<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameSeatStoreRequest;
use App\Http\Requests\Admin\GameSeatUpdateRequest;
use App\Models\Game;
use App\Models\GameSeat;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GameSeatController extends Controller
{
    /**
     * Seat an account in a game.
     *
     * An account gets at most one seat per game. If it already holds a retired
     * seat, that seat is reactivated rather than replaced.
     */
    public function store(GameSeatStoreRequest $request, Game $game): RedirectResponse
    {
        $game->seats()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seat added.')]);

        return to_route('admin.games.show', $game);
    }

    /**
     * Change a seat's game role, or retire and restore it.
     */
    public function update(GameSeatUpdateRequest $request, Game $game, GameSeat $seat): RedirectResponse
    {
        $seat->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seat updated.')]);

        return to_route('admin.games.show', $game);
    }
}
