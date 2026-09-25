<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Spec §8 — 020. */
#[Fillable(['tenant_id', 'date', 'visits', 'uniques', 'leads', 'top_paths'])]
class SiteStatsDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'site_stats_daily';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'visits' => 'integer',
            'uniques' => 'integer',
            'leads' => 'integer',
            'top_paths' => 'array',
        ];
    }
}
