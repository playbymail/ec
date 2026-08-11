<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed the ordinary member accounts a developer needs to click around with.
 *
 * Six verified members, user1@holos.test through user6@holos.test, each with
 * the matching password USER.1.s3cr3t ... USER.6.s3cr3t. Those passwords are
 * public, so this seeder refuses to run anywhere but local development and the
 * test suite. It skips accounts that already exist, so running it again after
 * you have renamed or promoted one of them changes nothing.
 */
class DevelopmentUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The number of member accounts to seed.
     */
    public const ACCOUNTS = 6;

    /**
     * Seed the development member accounts.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach (range(1, self::ACCOUNTS) as $number) {
            $email = self::email($number);

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = new User([
                'name' => "User {$number}",
                'email' => $email,
                'password' => self::password($number),
            ]);

            $user->email_verified_at = now();
            $user->save();
        }
    }

    /**
     * Get the email address of the numbered development account.
     */
    public static function email(int $number): string
    {
        return "user{$number}@holos.test";
    }

    /**
     * Get the password of the numbered development account.
     */
    public static function password(int $number): string
    {
        return "USER.{$number}.s3cr3t";
    }
}
