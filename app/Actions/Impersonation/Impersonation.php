<?php

namespace App\Actions\Impersonation;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Impersonation
{
    /**
     * The session key holding the id of the administrator who started the impersonation.
     */
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Determine whether the current session is impersonating someone.
     */
    public function isActive(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    /**
     * Resolve the administrator behind the current impersonation.
     *
     * Returns null when nobody is being impersonated, and also when the
     * account that started it has since been deleted or demoted — in that case
     * there is no longer an administrator to hand the session back to.
     */
    public function impersonator(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        $impersonator = User::query()->find($id);

        return $impersonator?->isAdmin() === true ? $impersonator : null;
    }

    /**
     * Sign the administrator in as the given user.
     *
     * Logging in migrates the session to a fresh id while keeping its data, so
     * the remembered impersonator survives but the pre-impersonation session
     * identifier does not.
     */
    public function start(Request $request, User $user, User $impersonator): void
    {
        Auth::login($user);

        $request->session()->put(self::SESSION_KEY, $impersonator->id);
    }

    /**
     * Hand the session back to the administrator who started the impersonation.
     */
    public function stop(Request $request, User $impersonator): void
    {
        $request->session()->forget(self::SESSION_KEY);

        Auth::login($impersonator);
    }

    /**
     * Abandon an impersonation that can no longer be handed back.
     */
    public function abandon(Request $request): void
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
