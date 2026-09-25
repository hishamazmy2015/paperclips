<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A listings feed (GenericXml, PropertyFinder, Bayut). Credentials encrypted at rest. Spec §8 — 009. */
#[Fillable(['tenant_id', 'provider', 'credentials', 'filters', 'schedule_minutes', 'last_sync_at', 'last_error', 'active'])]
class ListingFeed extends Model
{
    use BelongsToTenant, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'filters' => 'array',
            'schedule_minutes' => 'integer',
            'last_sync_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
