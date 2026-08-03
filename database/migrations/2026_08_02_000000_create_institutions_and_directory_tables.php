<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('whatsapp_number')->nullable();
            $table->string('join_code')->unique();
            $table->string('status')->default('Active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_teacher_profile_id')->nullable()->constrained('teacher_profiles')->nullOnDelete();
            $table->string('class_name');
            $table->string('section_name')->default('Section A');
            $table->string('join_code')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('subjects')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('teacher_profile_id')->nullable()->after('institution_id')->constrained('teacher_profiles')->nullOnDelete();
            $table->foreignId('class_section_id')->nullable()->after('teacher_profile_id')->constrained('class_sections')->nullOnDelete();
        });

        Schema::table('routines', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_section_id');
            $table->dropConstrainedForeignId('teacher_profile_id');
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('institutions');
    }
};
