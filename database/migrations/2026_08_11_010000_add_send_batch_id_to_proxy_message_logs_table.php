<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxy_message_logs', function (Blueprint $table) {
            $table->uuid('send_batch_id')->nullable()->after('provider_message_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('proxy_message_logs', function (Blueprint $table) {
            $table->dropIndex(['send_batch_id']);
            $table->dropColumn('send_batch_id');
        });
    }
};
