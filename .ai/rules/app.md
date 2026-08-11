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

## Games: seats are retired, never deleted, and game roles are not app roles
This application owns game metadata only — name, short name, status (`App\Enums\GameStatus`: setup, active, paused, completed, archived) and the seat roster. The game engine owns game state; do not model turns or state here.

`App\Enums\GameRole` (player | gamemaster) is a game concept with zero application permissions. It is unrelated to `App\Enums\UserRole`, which is what grants admin access. A gamemaster seat does not let anyone into /admin.

An account holds at most one seat per game — `game_seats` has a unique index on (game_id, user_id), and `GameSeatStoreRequest` rejects a duplicate with a pointed message. `GameSeat` has no destroy endpoint: retire a seat with `is_active = false` instead, because engine history keeps referring to it. That means the uniqueness check counts retired seats too, so bringing a departed account back is a reactivation, never a second row — `GameController::show` leaves already-seated accounts out of `assignableUsers` for exactly that reason.

`Game::activeSeats()` exists so `withCount(['seats', 'activeSeats'])` yields `active_seats_count` that Larastan can resolve — do not reach for a `seats as active_seats_count` closure alias.

Short names are uppercased in `GameStoreRequest`/`GameUpdateRequest` (they show up in turn reports and file names) and limited to 16 chars of [A-Z0-9-].

Seat routes are nested under a game inside `Route::scopeBindings()`, so a seat from another game 404s rather than being edited through the wrong game.
