<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Spec §8 — 021. */
#[Fillable(['tenant_id', 'reporter_email', 'reporter_ip', 'reason', 'details', 'status', 'reviewed_by', 'reviewed_at'])]
class AbuseReport extends Model
{
    use BelongsToTenant;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
