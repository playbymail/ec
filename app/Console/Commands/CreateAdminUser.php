<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAdminUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin
                            {email? : The email address of the administrator}
                            {--name= : The name to give a newly created account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account, or promote an existing account to administrator';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email') ?? $this->ask('Email address')));

        $user = User::where('email', $email)->first();

        return $user === null
            ? $this->createAdministrator($email)
            : $this->promoteToAdministrator($user);
    }

    /**
     * Create a new, verified administrator account.
     */
    private function createAdministrator(string $email): int
    {
        $input = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => $email,
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        try {
            Validator::make($input, [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ])->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $user->role = UserRole::Admin;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Created administrator {$user->email}.");

        return self::SUCCESS;
    }

    /**
     * Promote an existing account to administrator, leaving its password untouched.
     */
    private function promoteToAdministrator(User $user): int
    {
        if ($user->isAdmin()) {
            $this->info("{$user->email} is already an administrator.");

            return self::SUCCESS;
        }

        if (! $this->confirm("{$user->email} already exists. Promote this account to administrator?", true)) {
            $this->warn('Nothing changed.');

            return self::FAILURE;
        }

        $user->role = UserRole::Admin;
        $user->save();

        $this->info("Promoted {$user->email} to administrator.");

        return self::SUCCESS;
    }
}
