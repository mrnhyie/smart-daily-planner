<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('reminder_time');
            $table->index('last_whatsapp_reminded_on');
            $table->index('last_sms_reminded_on');
            $table->index('last_push_reminded_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['reminder_time']);
            $table->dropIndex(['last_whatsapp_reminded_on']);
            $table->dropIndex(['last_sms_reminded_on']);
            $table->dropIndex(['last_push_reminded_on']);
        });
    }
};
