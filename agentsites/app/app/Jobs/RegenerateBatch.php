<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Caching\Regenerator;
use App\Models\RegenerateRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One batch of `platform:site:regenerate --all`; chains the next batch itself (spec §16, A5). */
final class RegenerateBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $runId) {}

    public function handle(Regenerator $regenerator): void
    {
        $run = RegenerateRun::query()->find($this->runId);
        if ($run === null || $run->isDone()) {
            return;
        }
        $regenerator->processBatch($run);
    }
}
