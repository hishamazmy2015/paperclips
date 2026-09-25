<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 004 tenants. config holds only what the agent set; defaults merge at read time (§9).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 63)->unique();
            $table->enum('status', ['draft', 'live', 'suspended', 'deleted'])->default('draft');
            $table->string('theme_key', 40)->default('atlas');
            $table->jsonb('config');
            $table->unsignedInteger('config_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('onboarding_step')->default(0);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->jsonb('ai_generated_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('status');
        });

        DB::statement('CREATE INDEX tenants_config_gin ON tenants USING GIN (config)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
