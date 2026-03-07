<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin-user';

    protected $description = 'Create an admin panel user interactively';

    /**
     * @return int
     */
    public function handle(): int
    {
        $this->info('Creating a new admin panel user.');
        $this->newLine();

        $name = $this->ask('Name');

        $email = $this->ask('Email');

        $password = $this->secret('Password (min 8 characters)');

        $passwordConfirm = $this->secret('Confirm password');

        $roleChoice = $this->choice(
            'Role',
            [UserRole::Admin->label(), UserRole::Manager->label()],
            0,
        );

        $role = $roleChoice === UserRole::Admin->label()
            ? UserRole::Admin->value
            : UserRole::Manager->value;

        $validator = Validator::make([
            'name'                  => $name,
            'email'                 => $email,
            'password'              => $password,
            'password_confirmation' => $passwordConfirm,
        ], [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'name.required'         => 'Name is required.',
            'email.required'        => 'Email is required.',
            'email.email'           => 'Email must be a valid address.',
            'email.unique'          => 'A user with this email already exists.',
            'password.required'     => 'Password is required.',
            'password.min'          => 'Password must be at least 8 characters.',
            'password.confirmed'    => 'Passwords do not match.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => bcrypt($password),
            'role'     => $role,
        ]);

        $this->newLine();
        $this->info("User \"{$name}\" ({$email}) created with role \"{$roleChoice}\".");

        return self::SUCCESS;
    }
}
