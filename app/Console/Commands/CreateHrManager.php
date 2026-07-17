<?php

namespace App\Console\Commands;

use App\Services\HrRoleService;
use Illuminate\Console\Command;

class CreateHrManager extends Command
{
    protected $signature = 'hr:create-manager {email} {name} {--password=}';

    protected $description = 'Create an HR manager portal user (login at /hr)';

    public function handle(HrRoleService $hrRoles): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password') ?: str()->password(12);

        $user = $hrRoles->createManager($name, $email, $password);

        $this->info('HR manager created.');
        $this->line("Email: {$user->email}");
        $this->line("Password: {$password}");
        $this->line('Login URL: /hr');

        return self::SUCCESS;
    }
}
