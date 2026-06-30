<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('term_label')->nullable();
            $table->string('status')->default('Draft');
            $table->json('days');
            $table->json('periods');
            $table->json('classes');
            $table->json('teachers');
            $table->json('generation_rules')->nullable();
            $table->json('generated_grid')->nullable();
            $table->json('teacher_schedule')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
