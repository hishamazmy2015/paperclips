<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A listings feed (GenericXml, PropertyFinder). Credentials encrypted at rest. Spec §8 — 009.
 *
 * @property int $tenant_id
 * @property string $provider
 * @property mixed $credentials
 * @property array<string, mixed>|null $filters
 * @property int $schedule_minutes
 * @property Carbon|null $last_sync_at
 * @property string|null $last_error
 * @property bool $active
 */
#[Fillable(['tenant_id', 'provider', 'credentials', 'filters', 'schedule_minutes', 'last_sync_at', 'last_error', 'active'])]
class ListingFeed extends Model
{
    use BelongsToTenant, SoftDeletes;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'filters' => 'array',
            'schedule_minutes' => 'integer',
            'last_sync_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
