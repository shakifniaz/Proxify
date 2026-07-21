<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxy_runs', function (Blueprint $table) {
            $table->json('proxy_generated_grid')->nullable()->after('adjustments');
            $table->json('proxy_teacher_schedule')->nullable()->after('proxy_generated_grid');
            $table->timestamp('approved_at')->nullable()->after('metrics');
        });
    }

    public function down(): void
    {
        Schema::table('proxy_runs', function (Blueprint $table) {
            $table->dropColumn(['proxy_generated_grid', 'proxy_teacher_schedule', 'approved_at']);
        });
    }
};
