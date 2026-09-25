<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 020 site_stats_daily: first-party, server-side analytics (§6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_stats_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->jsonb('top_paths')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats_daily');
    }
};
