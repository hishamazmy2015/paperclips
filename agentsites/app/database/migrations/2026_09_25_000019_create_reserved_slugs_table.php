<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 019 reserved_slugs: runtime additions to config/reserved_slugs.php (admin, §15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserved_slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 63)->unique();
            $table->string('reason', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserved_slugs');
    }
};
