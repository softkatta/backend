<?php

namespace App\Console\Commands;

use Database\Seeders\CompanyRoleSeeder;
use Illuminate\Console\Command;

class SeedCompanyRoles extends Command
{
    protected $signature = 'company-roles:seed';

    protected $description = 'Seed default SoftKatta company roles master';

    public function handle(): int
    {
        $this->call(CompanyRoleSeeder::class);

        $this->info('Company roles master seeded successfully.');

        return self::SUCCESS;
    }
}
