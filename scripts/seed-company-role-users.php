<?php

use App\Enums\UserRole;
use App\Models\CompanyRole;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = 'SoftKatta@123';
$employeeService = app(EmployeeService::class);

$created = 0;
$updated = 0;
$skipped = 0;

echo "Company role users\n";
echo str_repeat('-', 72)."\n";

$roles = CompanyRole::query()->where('is_active', true)->orderBy('sort_order')->get();

if ($roles->isEmpty()) {
    echo "No company roles found. Run: php artisan company-roles:seed\n";
    exit(1);
}

foreach ($roles as $companyRole) {
    // Founder / Owner is a company job title — keep it separate from system Super Admin.
    $slug = Str::slug($companyRole->slug ?: $companyRole->name);
    $email = "staff.{$slug}@softkatta.com";
    $fullName = $companyRole->name;
    $department = $companyRole->category ?: 'General';

    $existingUser = User::query()->where('email', $email)->first();

    if ($existingUser) {
        $existingUser->update([
            'password' => $password,
            'initial_login_password' => $password,
            'name' => $fullName,
            'is_active' => true,
        ]);

        $employee = Employee::query()->where('user_id', $existingUser->id)->first()
            ?? Employee::query()->where('email', $email)->first();

        if ($employee) {
            $employee->update([
                'full_name' => $fullName,
                'email' => $email,
                'department' => $department,
                'company_role_id' => $companyRole->id,
                'designation' => $companyRole->name,
            ]);
        }

        echo sprintf("%-28s %s (refreshed)\n", $companyRole->name, $email);
        echo sprintf("%-28s Login: /employee | Password: %s\n\n", '', $password);
        $updated++;

        continue;
    }

    try {
        $result = $employeeService->createDirect([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => null,
            'department' => $department,
            'company_role_id' => $companyRole->id,
            'designation' => $companyRole->name,
            'portal_email' => $email,
        ]);

        $portalUser = $result['portal']['user'] ?? null;

        if ($portalUser) {
            $portalUser->update([
                'password' => $password,
                'initial_login_password' => $password,
                'name' => $fullName,
            ]);
        } elseif ($result['portal']['skipped'] ?? false) {
            echo sprintf("%-28s %s (portal skipped: %s)\n\n", $companyRole->name, $email, $result['portal']['reason'] ?? 'unknown');
            $skipped++;

            continue;
        }

        echo sprintf("%-28s %s (created)\n", $companyRole->name, $email);
        echo sprintf("%-28s Login: /employee | Password: %s\n\n", '', $password);
        $created++;
    } catch (Throwable $e) {
        echo sprintf("%-28s FAILED — %s\n\n", $companyRole->name, $e->getMessage());
        $skipped++;
    }
}

echo str_repeat('-', 72)."\n";
echo "Done: {$created} created, {$updated} updated/refreshed, {$skipped} skipped.\n";
echo "Default employee password: {$password}\n";
echo "View all login details in Admin → Users → View (eye icon).\n";
