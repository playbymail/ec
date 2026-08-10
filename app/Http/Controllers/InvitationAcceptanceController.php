<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Invitation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    /**
     * Show the form for accepting an invitation.
     */
    public function create(string $token): Response
    {
        $invitation = $this->findInvitation($token);

        if ($invitation === null || ! $invitation->isPending()) {
            return Inertia::render('auth/invitation-invalid', [
                'reason' => match (true) {
                    $invitation === null => 'invalid',
                    $invitation->isAccepted() => 'accepted',
                    default => 'expired',
                },
            ]);
        }

        return Inertia::render('auth/accept-invitation', [
            'email' => $invitation->email,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Accept an invitation and create the invited account.
     *
     * The new account is deliberately left unverified: clicking a link in an
     * email is not proof of control, so the invitee still receives, and must
     * complete, the standard email verification flow.
     */
    public function store(Request $request, string $token, CreateNewUser $creator): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        abort_if($invitation === null || ! $invitation->isPending(), 404);

        $user = $creator->create([
            ...$request->only(['name', 'password', 'password_confirmation']),
            'email' => $invitation->email,
        ]);

        $user->role = $invitation->role;
        $user->save();

        $invitation->accepted_at = now();
        $invitation->save();

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Find an invitation by its plain text token.
     */
    private function findInvitation(string $token): ?Invitation
    {
        return Invitation::where('token', Invitation::hashToken($token))->first();
    }
}
