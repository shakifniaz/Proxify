<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('class_section_id');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('settings'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('settings'));
    }
};
