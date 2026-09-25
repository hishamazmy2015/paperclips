<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Spec §8 — 016. */
#[Fillable(['coupon_id', 'account_id', 'subscription_id', 'redeemed_at'])]
class CouponRedemption extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime'];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
