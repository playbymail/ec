<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Invitation;
use App\Models\Session;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the administration areas and how much sits behind each one.
     *
     * The counts are four aggregates, one per area, so the page costs the same whatever the
     * size of the installation. Nothing here loads a model — a card that needed a row would be
     * a list, and the list already has its own screen.
     */
    public function index(): Response
    {
        return Inertia::render('admin/index', [
            'counts' => [
                'invitations' => Invitation::query()->pending()->count(),
                'users' => User::query()->count(),
                'games' => Game::query()->unarchived()->count(),
                'sessions' => Session::query()->whereNotNull('user_id')->count(),
            ],
        ]);
    }
}
