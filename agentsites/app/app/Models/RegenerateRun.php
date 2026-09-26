<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A `platform:site:regenerate --all` run: purge + warm every live site in batches with a
 * checkpoint (last_tenant_id), resumable after a kill (spec §16, A5).
 *
 * @property string $status
 * @property int $total_tenants
 * @property int $processed_tenants
 * @property int $warmed_pages
 * @property int $last_tenant_id
 * @property int $batch_size
 * @property bool $warm
 * @property string|null $last_error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['status', 'total_tenants', 'processed_tenants', 'warmed_pages', 'last_tenant_id', 'batch_size', 'warm', 'last_error', 'started_at', 'finished_at'])]
class RegenerateRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_tenants' => 'integer',
            'processed_tenants' => 'integer',
            'warmed_pages' => 'integer',
            'last_tenant_id' => 'integer',
            'batch_size' => 'integer',
            'warm' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isDone(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_tenants' => $this->total_tenants,
            'processed_tenants' => $this->processed_tenants,
            'warmed_pages' => $this->warmed_pages,
            'last_tenant_id' => $this->last_tenant_id,
            'last_error' => $this->last_error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
