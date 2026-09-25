<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 001 accounts. owner_user_id gets its foreign key in 002 (users depends on accounts).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['agent', 'brokerage'])->default('agent');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('plan_key', 40)->default('trial');
            $table->enum('plan_status', ['trialing', 'active', 'past_due', 'suspended', 'cancelled'])->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_customer_id', 120)->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('timezone', 64)->default('Asia/Dubai');
            $table->timestamps();
            $table->softDeletes();

            $table->index('plan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
