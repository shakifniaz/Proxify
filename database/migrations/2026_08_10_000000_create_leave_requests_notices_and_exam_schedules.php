<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('routine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('teacher_id');
            $table->string('teacher_name');
            $table->string('subject')->nullable();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days')->default(1);
            $table->string('duration');
            $table->json('periods')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('proxy_relevant')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index(['routine_id', 'teacher_id']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('board')->default('institutional');
            $table->string('title');
            $table->text('message');
            $table->string('urgency')->default('Normal');
            $table->string('visibility')->nullable();
            $table->json('acknowledged_by')->nullable();
            $table->unsignedInteger('read_count')->default(0);
            $table->timestamps();

            $table->index(['institution_id', 'board']);
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('routine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('Draft');
            $table->json('halls')->nullable();
            $table->json('time_slots')->nullable();
            $table->json('class_options')->nullable();
            $table->json('subject_options')->nullable();
            $table->json('invigilator_options')->nullable();
            $table->json('exam_grid')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('leave_requests');
    }
};
