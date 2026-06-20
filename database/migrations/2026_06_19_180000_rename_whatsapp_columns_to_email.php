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
            $table->renameColumn('reminder_whatsapp', 'reminder_email');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('last_whatsapp_reminded_on', 'last_email_reminded_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('reminder_email', 'reminder_whatsapp');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('last_email_reminded_on', 'last_whatsapp_reminded_on');
        });
    }
};
