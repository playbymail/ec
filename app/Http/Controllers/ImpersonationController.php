<?php

namespace App\Http\Controllers;

use App\Actions\Impersonation\Impersonation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * Sign the administrator in as another account.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        abort_if($user->is($impersonator), 403);
        abort_if($user->isAdmin(), 403);

        $this->impersonation->start($request, $user, $impersonator);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('You are now signed in as :name.', ['name' => $user->name]),
        ]);

        return to_route('dashboard');
    }

    /**
     * Hand the session back to the administrator who started the impersonation.
     *
     * This route sits outside the admin group on purpose: the session is signed
     * in as a member for as long as the impersonation lasts, so the admin
     * middleware would lock the way back out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->impersonation->isActive($request), 403);

        $impersonator = $this->impersonation->impersonator($request);

        if ($impersonator === null) {
            $this->impersonation->abandon($request);

            return to_route('login');
        }

        $this->impersonation->stop($request, $impersonator);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Impersonation ended.')]);

        return to_route('admin.users.index');
    }
}
