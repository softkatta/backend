<?php

namespace Database\Seeders;

use App\Models\CompanyRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanyRoleSeeder extends Seeder
{
    /**
     * @return list<array{name: string, category: string}>
     */
    public static function roles(): array
    {
        return [
            ['name' => 'Founder / Owner', 'category' => 'Leadership'],
            ['name' => 'Director', 'category' => 'Leadership'],
            ['name' => 'CEO (Chief Executive Officer)', 'category' => 'Leadership'],
            ['name' => 'CTO (Chief Technology Officer)', 'category' => 'Leadership'],
            ['name' => 'Project Manager', 'category' => 'Engineering & Delivery'],
            ['name' => 'Team Lead', 'category' => 'Engineering & Delivery'],
            ['name' => 'Senior Software Developer', 'category' => 'Engineering & Delivery'],
            ['name' => 'Software Developer', 'category' => 'Engineering & Delivery'],
            ['name' => 'UI/UX Designer', 'category' => 'Design'],
            ['name' => 'QA Tester', 'category' => 'Quality & Infrastructure'],
            ['name' => 'DevOps Engineer', 'category' => 'Quality & Infrastructure'],
            ['name' => 'IT Admin', 'category' => 'Quality & Infrastructure'],
            ['name' => 'Business Analyst', 'category' => 'Business & Analysis'],
            ['name' => 'Sales Executive', 'category' => 'Sales & Marketing'],
            ['name' => 'Business Development Executive (BDE)', 'category' => 'Sales & Marketing'],
            ['name' => 'Digital Marketing Executive', 'category' => 'Sales & Marketing'],
            ['name' => 'Customer Support Executive', 'category' => 'Customer & Support'],
            ['name' => 'Technical Support Engineer', 'category' => 'Customer & Support'],
            ['name' => 'Implementation Executive', 'category' => 'Customer & Support'],
            ['name' => 'HR Executive', 'category' => 'Human Resources'],
            ['name' => 'Recruiter', 'category' => 'Human Resources'],
            ['name' => 'Accountant', 'category' => 'Finance & Accounts'],
            ['name' => 'Billing Executive', 'category' => 'Finance & Accounts'],
            ['name' => 'Admin Executive', 'category' => 'Administration'],
            ['name' => 'Office Administrator', 'category' => 'Administration'],
            ['name' => 'Receptionist', 'category' => 'Administration'],
            ['name' => 'Intern / Trainee', 'category' => 'Internship'],
        ];
    }

    public function run(): void
    {
        foreach (self::roles() as $index => $role) {
            CompanyRole::updateOrCreate(
                ['slug' => Str::slug($role['name'])],
                [
                    'name' => $role['name'],
                    'category' => $role['category'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
