<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One CSV import: counters and the resume checkpoint (spec §12).
 *
 * @property array<string, mixed>|null $options
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['source', 'status', 'options', 'total_rows', 'processed_rows', 'created_rows', 'existing_rows', 'error_rows', 'last_row', 'errors_path', 'last_error', 'started_at', 'finished_at'])]
class ImportRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_rows' => 'integer',
            'existing_rows' => 'integer',
            'error_rows' => 'integer',
            'last_row' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function batchSize(): int
    {
        return max(1, (int) ($this->options['batch'] ?? 100));
    }

    public function publishes(): bool
    {
        return (bool) ($this->options['publish'] ?? true);
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'created_rows' => $this->created_rows,
            'existing_rows' => $this->existing_rows,
            'error_rows' => $this->error_rows,
            'last_row' => $this->last_row,
            'errors_path' => $this->errors_path,
            'last_error' => $this->last_error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'seconds' => $this->started_at !== null && $this->finished_at !== null ? $this->finished_at->diffInSeconds($this->started_at, true) : null,
        ];
    }
}
