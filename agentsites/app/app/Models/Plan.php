<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Reporting mirror of config/plans.php. Spec §8 — 013. */
#[Fillable(['key', 'name', 'price_monthly', 'price_yearly', 'currency', 'limits', 'features', 'active', 'synced_at'])]
class Plan extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'limits' => 'array',
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
