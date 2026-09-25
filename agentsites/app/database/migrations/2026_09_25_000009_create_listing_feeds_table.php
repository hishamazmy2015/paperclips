<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 009 listing_feeds. credentials are encrypted at the model (§17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->text('credentials')->nullable();
            $table->jsonb('filters')->nullable();
            $table->unsignedInteger('schedule_minutes')->default(30);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'active']);
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->foreign('feed_id')->references('id')->on('listing_feeds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['feed_id']);
        });
        Schema::dropIfExists('listing_feeds');
    }
};
