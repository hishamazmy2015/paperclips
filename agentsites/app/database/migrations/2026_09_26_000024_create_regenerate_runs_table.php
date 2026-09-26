<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §16 / A5: checkpoint table for `platform:site:regenerate --all` (purge + warm in batches).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regenerate_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_tenants')->default(0);
            $table->unsignedInteger('processed_tenants')->default(0);
            $table->unsignedInteger('warmed_pages')->default(0);
            $table->unsignedBigInteger('last_tenant_id')->default(0);
            $table->unsignedSmallInteger('batch_size')->default(50);
            $table->boolean('warm')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regenerate_runs');
    }
};
