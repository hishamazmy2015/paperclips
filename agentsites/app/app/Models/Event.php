<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A funnel/product event row in the monthly-partitioned events table. Spec §8 — 017.
 * Append-only: written through App\Platform\EventLog, never updated.
 *
 * @property string $name
 * @property int|null $tenant_id
 * @property int|null $account_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property array<string, mixed>|null $properties
 * @property string|null $device
 * @property string|null $locale
 * @property Carbon $created_at
 */
#[Fillable(['name', 'tenant_id', 'account_id', 'user_id', 'session_id', 'properties', 'device', 'locale', 'ip', 'created_at'])]
class Event extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
