<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\ImportRun;

/** platform:import:status [id] — progress of an import run (or the latest runs). */
final class ImportStatusCommand extends PlatformCommand
{
    protected $signature = 'platform:import:status {id? : Import run id (latest 10 when omitted)}';

    protected $description = 'Show the progress and errors of CSV import runs';

    public function handle(): int
    {
        $id = $this->argument('id');
        if (is_string($id) && $id !== '') {
            $run = ImportRun::query()->find((int) $id);
            if ($run === null) {
                return $this->failWith("no import run #{$id}");
            }

            return $this->emit(['ok' => true] + $run->summary());
        }

        $runs = ImportRun::query()->latest('id')->limit(10)->get()->map(fn (ImportRun $run): array => $run->summary())->all();

        return $this->emit(['ok' => true, 'runs' => $runs]);
    }
}
