<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\HrRoleService;
use App\Services\TenantService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = 'SoftKatta@123';

$definitions = [
    'client' => [
        'email' => 'demo-customer@softkatta.com',
        'name' => 'Demo Customer',
        'company_name' => 'Demo Customer Pvt Ltd',
        'login' => '/login',
    ],
    'hr_manager' => [
        'email' => 'demo-hr@softkatta.com',
        'name' => 'Demo HR Manager',
        'login' => '/hr',
    ],
];

$removedDemoAdmin = User::query()
    ->where('email', 'demo-admin@softkatta.com')
    ->where('role', UserRole::SuperAdmin)
    ->delete();

$created = [];
$existing = [];
$updated = [];

foreach ($definitions as $role => $def) {
    $user = User::query()->where('email', $def['email'])->first();

    if ($user) {
        $user->update(['initial_login_password' => $password]);
        $existing[] = $role;
        $updated[] = $role;

        continue;
    }

    match ($role) {
        'client' => (function () use ($def, $password, &$created, $role): void {
            $user = User::create([
                'name' => $def['name'],
                'email' => $def['email'],
                'password' => $password,
                'initial_login_password' => $password,
                'phone' => '+919876543210',
                'company_name' => $def['company_name'],
                'role' => UserRole::Client,
                'two_factor_email_enabled' => true,
                'is_active' => true,
            ]);
            $user->assignRole('client');
            $tenant = app(TenantService::class)->create([
                'name' => $def['company_name'],
            ], $user);
            $user->update(['tenant_id' => $tenant->id]);
            $created[$role] = $user->email;
        })(),
        'hr_manager' => (function () use ($def, $password, &$created, $role): void {
            $user = app(HrRoleService::class)->createManager($def['name'], $def['email'], $password);
            $created[$role] = $user->email;
        })(),
        default => null,
    };
}

echo "Portal role users (super admin excluded)\n";
echo str_repeat('-', 48)."\n";

if ($removedDemoAdmin) {
    echo "Removed demo super admin: demo-admin@softkatta.com\n\n";
}

foreach ($definitions as $role => $def) {
    if (isset($created[$role])) {
        $status = 'created';
    } elseif (in_array($role, $updated, true)) {
        $status = 'already exists (credentials refreshed)';
    } else {
        $status = 'skipped';
    }

    echo sprintf("%-12s %s (%s)\n", strtoupper($role), $def['email'], $status);
    echo sprintf("%-12s Login: %s\n", '', $def['login']);
    echo sprintf("%-12s Password: %s\n", '', $password);
    echo "\n";
}

echo str_repeat('-', 48)."\n";
echo "Run scripts/seed-company-role-users.php for all company role employees.\n";
