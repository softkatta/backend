<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--password= : Admin password (min 8 characters)}
                            {--name=SoftKatta Admin : Display name}';

    protected $description = 'Create a super admin user for the admin portal';

    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password (min 8 chars)');
        $name = (string) $this->option('name');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'two_factor_email_enabled' => true,
            'is_active' => true,
            'country' => 'India',
        ]);

        $user->assignRole('super_admin');

        $this->info("Admin user created: {$user->email}");
        $this->line('Sign in at /admin with the credentials above.');

        return self::SUCCESS;
    }
}
