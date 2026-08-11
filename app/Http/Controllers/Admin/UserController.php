<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show every account on the application.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->withCount('sessions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'email_verified' => $user->hasVerifiedEmail(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'sessions_count' => $user->sessions_count,
                'created_at' => $user->created_at?->toIso8601String(),
                'is_self' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
        ]);
    }

    /**
     * Change a user's role.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->abortIfSelf($request, $user);

        $user->role = UserRole::from($request->string('role')->toString());
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Delete a user along with everything that lets them back in.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->abortIfSelf($request, $user);

        $user->sessions()->delete();
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Refuse actions an administrator aims at their own account.
     *
     * An administrator demoting or deleting themselves could strand the
     * application without an administrator; deleting your own account is what
     * the profile settings page is for.
     */
    private function abortIfSelf(Request $request, User $user): void
    {
        abort_if($user->is($request->user()), 403);
    }
}
