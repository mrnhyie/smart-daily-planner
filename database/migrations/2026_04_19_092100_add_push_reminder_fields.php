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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reminder_push')->default(true)->after('reminder_sms');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('last_push_reminded_on')->nullable()->after('last_sms_reminded_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('last_push_reminded_on');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reminder_push');
        });
    }
};
