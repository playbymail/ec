<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameStoreRequest;
use App\Http\Requests\Admin\GameUpdateRequest;
use App\Models\Game;
use App\Models\GameSeat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Show every game the application knows about.
     */
    public function index(): Response
    {
        $games = Game::query()
            ->withCount(['seats', 'activeSeats'])
            ->orderBy('name')
            ->get()
            ->map(fn (Game $game): array => [
                'id' => $game->id,
                'name' => $game->name,
                'short_name' => $game->short_name,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'seats_count' => $game->seats_count,
                'active_seats_count' => $game->active_seats_count,
                'created_at' => $game->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/games/index', [
            'games' => $games,
        ]);
    }

    /**
     * Create a game. It starts in setup, with no seats.
     */
    public function store(GameStoreRequest $request): RedirectResponse
    {
        $game = Game::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game created.')]);

        return to_route('admin.games.show', $game);
    }

    /**
     * Show a game's metadata and the accounts seated in it.
     */
    public function show(Game $game): Response
    {
        $seats = $game->seats()
            ->with('user')
            ->get()
            ->sortBy([
                fn (GameSeat $seat): int => $seat->is_active ? 0 : 1,
                fn (GameSeat $seat): string => $seat->user->name,
                fn (GameSeat $seat): int => $seat->id,
            ])
            ->values()
            ->map(fn (GameSeat $seat): array => [
                'id' => $seat->id,
                'user_id' => $seat->user_id,
                'user_name' => $seat->user->name,
                'user_email' => $seat->user->email,
                'role' => $seat->role->value,
                'role_label' => $seat->role->label(),
                'is_active' => $seat->is_active,
                'created_at' => $seat->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/games/show', [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'short_name' => $game->short_name,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'created_at' => $game->created_at?->toIso8601String(),
            ],
            'seats' => $seats,
            'statuses' => array_map(
                fn (GameStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                GameStatus::cases(),
            ),
            'gameRoles' => array_map(
                fn (GameRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                GameRole::cases(),
            ),
            // Accounts already holding a seat are left out, retired ones
            // included: their existing seat is reactivated instead.
            'assignableUsers' => User::query()
                ->whereNotIn('id', $game->seats()->select('user_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
        ]);
    }

    /**
     * Update a game's metadata.
     */
    public function update(GameUpdateRequest $request, Game $game): RedirectResponse
    {
        $game->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game updated.')]);

        return to_route('admin.games.show', $game);
    }

    /**
     * Delete a game along with every seat in it.
     */
    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game deleted.')]);

        return to_route('admin.games.index');
    }
}
