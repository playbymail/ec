---
paths:
  - 'database/seeders/**'
---

# Seeders

## Development member accounts come from DevelopmentUserSeeder
`Database\Seeders\DevelopmentUserSeeder` seeds six verified members, user1@holos.test .. user6@holos.test, each with the matching password USER.1.s3cr3t .. USER.6.s3cr3t (`DevelopmentUserSeeder::email()` / `::password()` — use those helpers in tests rather than hardcoding).

Because those passwords are public, the seeder returns early unless `app()->environment(['local', 'testing'])`. Keep that guard on any seeder that mints known credentials. It also skips accounts that already exist, so it is safe to re-run after renaming or promoting one of them.

`DatabaseSeeder` calls it, so plain `php artisan db:seed` includes the six. Seeders here must not depend on `$this->command` for output: PHPStan reads the framework's `Seeder::$command` docblock as always-set, so both `?->` and `isset()` guards fail analysis while a direct `run()` call (as the tests do) has no command at all.
