<?php

namespace App\Actions\Invitations;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

class IssueInvitation
{
    /**
     * Issue an invitation for the given email address and email it out.
     *
     * Re-issuing an invitation for an address that already has one replaces the
     * token, so a resend invalidates any link from the previous email. The plain
     * text token is never persisted, so this is the only way to resend one.
     */
    public function __invoke(string $email, UserRole $role, ?User $invitedBy): Invitation
    {
        $token = Invitation::generateToken();

        $invitation = Invitation::firstOrNew(['email' => $email]);

        $invitation->role = $role;
        $invitation->token = Invitation::hashToken($token);
        $invitation->invited_by_id = $invitedBy?->id;
        $invitation->expires_at = now()->addDays(Invitation::EXPIRES_AFTER_DAYS);
        $invitation->accepted_at = null;
        $invitation->save();

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $token));

        return $invitation;
    }
}
