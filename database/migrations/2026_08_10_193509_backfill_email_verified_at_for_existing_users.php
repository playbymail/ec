<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Until `User` implemented `MustVerifyEmail`, the application never sent a
     * verification email and never enforced the `verified` middleware, so every
     * existing account has a null `email_verified_at` through no fault of its
     * own. Grandfather those accounts in rather than locking them out.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Irreversible: once backfilled, a grandfathered account is indistinguishable
     * from an account that genuinely verified its email address.
     */
    public function down(): void
    {
        //
    }
};
