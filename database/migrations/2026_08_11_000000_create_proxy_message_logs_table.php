<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->nullable()->constrained('teacher_profiles')->nullOnDelete();
            $table->string('teacher_name');
            $table->string('whatsapp_number')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider')->default('whatsapp_cloud');
            $table->string('provider_message_id')->nullable();
            $table->text('message_body');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_message_logs');
    }
};
