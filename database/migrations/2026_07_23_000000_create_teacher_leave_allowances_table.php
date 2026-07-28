<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_leave_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('teacher_id');
            $table->string('teacher_name');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('max_leaves')->default(12);
            $table->timestamps();

            $table->unique(['routine_id', 'teacher_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_leave_allowances');
    }
};
