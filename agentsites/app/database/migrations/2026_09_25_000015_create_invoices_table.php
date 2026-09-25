<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §8 — 015 invoices (5% VAT + TRN, §14). Kept 90 days after deletion (§8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('provider', 20);
            $table->string('provider_invoice_id', 120)->nullable();
            $table->enum('status', ['draft', 'open', 'paid', 'void', 'uncollectible'])->default('draft');
            $table->string('currency', 3)->default('AED');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('vat_rate', 5, 4)->default(0.05);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('trn', 40)->nullable();
            $table->jsonb('lines');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
