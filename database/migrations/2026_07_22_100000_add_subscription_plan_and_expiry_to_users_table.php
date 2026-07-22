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
            $table->string('subscription_plan')->nullable()->after('subscribed');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
            $table->string('last_payment_reference')->nullable()->after('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan', 'subscription_expires_at', 'last_payment_reference']);
        });
    }
};
