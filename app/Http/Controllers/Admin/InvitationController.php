<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invitations\IssueInvitation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvitationStoreRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show every invitation that has been issued.
     */
    public function index(): Response
    {
        $invitations = Invitation::with('invitedBy')
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'status' => match (true) {
                    $invitation->isAccepted() => 'accepted',
                    $invitation->isExpired() => 'expired',
                    default => 'pending',
                },
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/invitations/index', [
            'invitations' => $invitations,
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
        ]);
    }

    /**
     * Issue a new invitation.
     */
    public function store(InvitationStoreRequest $request, IssueInvitation $issueInvitation): RedirectResponse
    {
        $issueInvitation(
            $request->string('email')->toString(),
            UserRole::from($request->string('role')->toString()),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('admin.invitations.index');
    }

    /**
     * Issue a fresh token for an invitation and email it again.
     */
    public function update(Request $request, Invitation $invitation, IssueInvitation $issueInvitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 403);

        $issueInvitation($invitation->email, $invitation->role, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return to_route('admin.invitations.index');
    }

    /**
     * Revoke an invitation, invalidating its link.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return to_route('admin.invitations.index');
    }
}
