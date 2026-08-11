<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    /**
     * Show every signed-in session across the application.
     */
    public function index(Request $request): Response
    {
        $currentId = $request->session()->getId();

        $sessions = Session::query()
            ->with('user:id,name,email')
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (Session $session): array => [
                'digest' => $session->digest(),
                'user' => [
                    'id' => $session->user?->id,
                    'name' => $session->user?->name,
                    'email' => $session->user?->email,
                ],
                'ip_address' => $session->ip_address,
                'browser' => $session->browser(),
                'platform' => $session->platform(),
                'last_active_at' => $session->lastActiveAt()->toIso8601String(),
                'is_current' => $session->id === $currentId,
            ]);

        return Inertia::render('admin/sessions/index', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Sign out a single session.
     */
    public function destroy(Request $request, string $digest): RedirectResponse
    {
        $session = Session::findByDigest($digest);

        abort_if($session?->user_id === null, 404);
        abort_if($session->id === $request->session()->getId(), 403);

        $session->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session signed out.')]);

        return to_route('admin.sessions.index');
    }

    /**
     * Sign out every session except the one making the request.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        Session::query()
            ->whereNotNull('user_id')
            ->whereKeyNot($request->session()->getId())
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All other sessions signed out.')]);

        return to_route('admin.sessions.index');
    }
}
