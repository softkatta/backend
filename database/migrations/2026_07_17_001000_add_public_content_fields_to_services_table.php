<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('body')->nullable()->after('description');
            $table->string('bullets_heading')->nullable()->after('body');
            $table->json('bullets')->nullable()->after('bullets_heading');
            $table->string('meta_title')->nullable()->after('bullets');
            $table->text('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['body', 'bullets_heading', 'bullets', 'meta_title', 'meta_description']);
        });
    }
};
