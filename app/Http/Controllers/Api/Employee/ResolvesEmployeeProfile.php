<?php

namespace App\Http\Controllers\Api\Employee;

use App\Models\Employee;
use Illuminate\Http\Request;

trait ResolvesEmployeeProfile
{
    protected function employeeFor(Request $request): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $request->user()?->id)
            ->first();

        abort_unless($employee, 404, 'Employee profile not linked to this account.');

        return $employee;
    }
}
