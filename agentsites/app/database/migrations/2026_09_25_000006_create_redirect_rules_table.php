<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 006 redirect_rules (renames, base-domain change, alias hosts). Path + query preserved.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirect_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('from_host', 253)->unique();
            $table->string('to_host', 253);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_rules');
    }
};
