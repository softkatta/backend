<?php

use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->where('email', 'demo-employee@softkatta.com')->first();

if (! $user) {
    echo "demo-employee not found\n";
    exit(0);
}

$employee = $user->employeeProfile;

if ($employee) {
    app(EmployeeService::class)->delete($employee);
    echo "Removed demo-employee@softkatta.com\n";
    exit(0);
}

$user->delete();
echo "Removed demo-employee user\n";
