<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §13 abandonment: reminders at +1 h and +24 h (once each, per draft) and an opt-out.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('reminder_1h_sent_at')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('reminders_opted_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['reminder_1h_sent_at', 'reminder_24h_sent_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reminders_opted_out_at');
        });
    }
};
