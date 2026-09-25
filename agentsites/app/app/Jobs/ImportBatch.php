<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Provisioning\ImportAgents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** One batch of an agents CSV import (spec §12); queues the next batch when done. */
final class ImportBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $runId, public readonly int $from, public readonly int $to) {}

    public function handle(ImportAgents $importer): void
    {
        $run = ImportRun::query()->find($this->runId);
        if ($run === null || $run->isDone()) {
            return;
        }
        $importer->processBatch($run, $this->from, $this->to);
    }

    public function failed(?Throwable $e): void
    {
        ImportRun::query()->where('id', $this->runId)->update([
            'status' => ImportRun::STATUS_FAILED,
            'last_error' => $e?->getMessage() ?? 'batch failed',
        ]);
    }
}
