<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Spec §8 — 015 (5% VAT, TRN; Phase 5). Purged 90 days after deletion. */
#[Fillable(['account_id', 'subscription_id', 'number', 'provider', 'provider_invoice_id', 'status', 'currency', 'subtotal', 'vat_rate', 'vat_amount', 'total', 'trn', 'lines', 'issued_at', 'due_at', 'paid_at', 'pdf_path'])]
class Invoice extends Model
{
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'lines' => 'array',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
