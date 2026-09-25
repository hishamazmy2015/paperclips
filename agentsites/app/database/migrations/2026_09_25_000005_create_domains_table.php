<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 005 domains. host is unique across the whole platform, soft-deleted rows included,
// so a released host cannot be claimed by another tenant before the purge window ends.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('host', 253)->unique();
            $table->enum('type', ['subdomain', 'custom']);
            $table->enum('role', ['primary', 'alias'])->default('primary');
            $table->boolean('verified')->default(false);
            $table->string('verification_token', 64)->nullable();
            $table->enum('dns_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->enum('ssl_status', ['none', 'pending', 'issued', 'failed'])->default('none');
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
