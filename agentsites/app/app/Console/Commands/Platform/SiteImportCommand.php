<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\ImportRun;
use App\Provisioning\ImportAgents;
use Throwable;

/**
 * platform:site:import f.csv [--dry-run --batch=100 --resume] — spec §12, §15, acceptance test A5.
 * Rows become sites in batches through the queue; a killed run resumes from its checkpoint.
 */
final class SiteImportCommand extends PlatformCommand
{
    protected $signature = 'platform:site:import
        {file? : Agents CSV (samples/agents.csv shape)}
        {--dry-run : Validate every row, create nothing}
        {--batch=100 : Rows per queued batch}
        {--resume= : Resume the import run with this id from its checkpoint}
        {--draft : Import as drafts instead of publishing}';

    protected $description = 'Import agents from a CSV: one site per row, batched, resumable, with a per-row error file';

    public function handle(ImportAgents $importer): int
    {
        $resume = $this->option('resume');
        if (is_string($resume) && $resume !== '') {
            $run = ImportRun::query()->find((int) $resume);
            if ($run === null) {
                return $this->failWith("no import run #{$resume}");
            }
            $run = $importer->resume($run);

            return $this->emit(['ok' => true, 'resumed' => true] + $run->summary(), sprintf('Resumed import #%d: %s (%d/%d rows)', $run->id, $run->status, $run->processed_rows, $run->total_rows));
        }

        $file = (string) $this->argument('file');
        if ($file === '' || ! is_file($file)) {
            return $this->failWith('usage: platform:site:import <file.csv> [--dry-run] [--batch=100] [--draft] | --resume=<id>');
        }

        try {
            if ((bool) $this->option('dry-run')) {
                $run = $importer->dryRun($file);

                return $this->emit(['ok' => $run->error_rows === 0, 'dry_run' => true] + $run->summary(), sprintf('Dry run: %d rows, %d with errors%s', $run->total_rows, $run->error_rows, $run->errors_path !== null ? ' → '.$run->errors_path : ''));
            }

            $run = $importer->start($file, max(1, (int) $this->option('batch')), ! (bool) $this->option('draft'));
        } catch (Throwable $e) {
            return $this->failWith($e->getMessage());
        }

        return $this->emit(['ok' => $run->status !== ImportRun::STATUS_FAILED] + $run->summary(), sprintf(
            'Import #%d %s: %d/%d rows, %d created, %d existing, %d errors%s',
            $run->id, $run->status, $run->processed_rows, $run->total_rows, $run->created_rows, $run->existing_rows, $run->error_rows,
            $run->errors_path !== null ? ' → '.$run->errors_path : '',
        ));
    }
}
