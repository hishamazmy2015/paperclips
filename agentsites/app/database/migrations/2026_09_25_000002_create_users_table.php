<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 002 users. No password column: auth is Google OAuth, email code/magic link and
// WhatsApp OTP (§6). email is unique but nullable so CLI/CSV-created agents can exist before
// they claim their account with an email.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('name', 120);
            $table->enum('role', ['owner', 'member'])->default('owner');
            $table->string('auth_provider', 20)->default('email');
            $table->string('google_id', 64)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
        });
        Schema::dropIfExists('users');
    }
};
