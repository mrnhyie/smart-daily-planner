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
            $table->date('last_whatsapp_reminded_on')->nullable()->after('task_order');
            $table->date('last_sms_reminded_on')->nullable()->after('last_whatsapp_reminded_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['last_whatsapp_reminded_on', 'last_sms_reminded_on']);
        });
    }
};
