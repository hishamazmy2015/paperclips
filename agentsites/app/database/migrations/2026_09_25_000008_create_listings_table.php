<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 008 listings. feed_id gets its foreign key in 009.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 80);
            $table->string('title_en', 200);
            $table->string('title_ar', 200)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->enum('offering', ['sale', 'rent']);
            $table->string('property_type', 40);
            $table->decimal('price', 14, 2);
            $table->string('currency', 3)->default('AED');
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->decimal('area_sqft', 10, 2)->nullable();
            $table->string('community', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->enum('status', ['available', 'sold', 'rented', 'hidden'])->default('available');
            $table->enum('source', ['manual', 'csv', 'feed', 'demo'])->default('manual');
            $table->unsignedBigInteger('feed_id')->nullable();
            $table->string('feed_ref', 120)->nullable();
            $table->boolean('featured')->default(false);
            $table->jsonb('media')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'ref']);
            $table->index(['tenant_id', 'status', 'featured']);
            $table->index(['tenant_id', 'community']);
            $table->index(['tenant_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
