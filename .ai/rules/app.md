---
paths:
  - 'app/**'
---

# App

## User roles are an enum column, deliberately not fillable
`users.role` casts to `App\Enums\UserRole` (Admin | Member) and defaults to Member both in the migration and in `User::$attributes`.

`role` is intentionally absent from the `#[Fillable]` attribute on `User`, so registration and profile updates cannot escalate a user to admin by posting a `role` field. Assign it explicitly (`$user->role = UserRole::Admin`) — never add it to the fillable list.

Enforce admin-only routes with the `admin` middleware alias (`App\Http\Middleware\EnsureUserIsAdmin`), paired with `auth`.

The only supported way to mint the first administrator — including in production — is `php artisan app:create-admin`. It is idempotent: it promotes an existing account rather than failing, and always prompts for the password so it never lands in shell history.

## Registration is invite-only
`Features::registration()` is deliberately absent from `config/fortify.php`, so `/register` does not exist. The only way to create an account is to accept an invitation: `App\Http\Controllers\InvitationAcceptanceController`, which delegates to the same `App\Actions\Fortify\CreateNewUser` action so password and profile rules stay in one place. Do not re-enable the Fortify feature to "fix" a missing register route.

Invitation tokens are stored as a sha256 hash (`Invitation::hashToken()`); only the emailed link carries the plain text. That means a token cannot be recovered — resending an invitation issues a new one and invalidates the old link. `App\Actions\Invitations\IssueInvitation` is the single place that mints tokens and sends mail; both the create and resend endpoints go through it.

Accepting an invitation does NOT verify the email address. Clicking a mailed link is not proof of control, so the invitee still completes the standard `MustVerifyEmail` flow afterwards.

## Session rows are addressed by digest, never by raw session id
`App\Models\Session` maps the `sessions` table written by the database session driver (SESSION_DRIVER=database in production, array in tests).

Its primary key IS the live session identifier: anything holding it can impersonate that browser. So the admin sessions screen and `admin.sessions.destroy` address a session by `Session::digest()` (sha256 of the id) and resolve it with `Session::findByDigest()`, which compares in PHP because SQLite has no sha2(). Never send a raw session id to the frontend or accept one as a route parameter.

The `sessions.user_id` foreign key is not constrained, so deleting a user does not cascade — `App\Http\Controllers\Admin\UserController::destroy` deletes `$user->sessions()` explicitly. Passkeys do cascade.

Administrators cannot change their own role, delete their own account, or sign their own browser out (403 on all three) — that is what keeps the last administrator from locking everyone out.
