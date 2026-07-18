<?php

use App\Models\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['name' => 'IT Admin', 'category' => 'Quality & Infrastructure', 'sort_order' => 26],
            ['name' => 'Director', 'category' => 'Leadership', 'sort_order' => 2],
        ];

        foreach ($roles as $role) {
            CompanyRole::firstOrCreate(
                ['slug' => Str::slug($role['name'])],
                [
                    'name' => $role['name'],
                    'category' => $role['category'],
                    'sort_order' => $role['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        CompanyRole::query()
            ->whereIn('slug', ['it-admin', 'director'])
            ->delete();
    }
};
