<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 011 media. Files live under {media_root}/{tenant_id}/ (§17); variants = thumb/card/hero.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('media');
            $table->string('path', 400);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->jsonb('variants')->nullable();
            $table->string('alt_en', 200)->nullable();
            $table->string('alt_ar', 200)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
