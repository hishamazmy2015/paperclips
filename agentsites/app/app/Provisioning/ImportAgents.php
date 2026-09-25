<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Jobs\ImportBatch;
use App\Models\ImportRun;
use App\Models\Tenant;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Themes\ThemeRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Import(csv) — spec §12: rows → ProvisionInput → ProvisionTenant in batches via the queue,
 * with a checkpoint row so a killed run resumes where it stopped; --dry-run validates only;
 * per-row errors go to <source>.errors.csv. 1000 rows in well under the 15-minute target.
 */
final class ImportAgents
{
    public function __construct(
        private readonly ProvisionTenant $provision,
        private readonly AccountResolver $accounts,
        private readonly PhoneNormalizer $phones,
        private readonly Slugs $slugs,
        private readonly ThemeRegistry $themes,
    ) {}

    /**
     * Validate every row without touching the database. Returns a completed dry-run record
     * (not persisted) with counts and the error file path.
     */
    public function dryRun(string $path): ImportRun
    {
        $total = AgentCsv::count($path);
        $errors = [];
        foreach (AgentCsv::rows($path, 1, $total) as $number => $row) {
            $problem = $this->validateRow($row);
            if ($problem !== null) {
                $errors[] = [$number, (string) ($row['slug'] ?? ''), $problem];
            }
        }
        $errorsPath = $errors === [] ? null : $this->writeErrors($path, $errors, append: false);

        return new ImportRun([
            'source' => $path,
            'status' => ImportRun::STATUS_COMPLETED,
            'options' => ['dry_run' => true],
            'total_rows' => $total,
            'processed_rows' => $total,
            'error_rows' => count($errors),
            'errors_path' => $errorsPath,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /** Create the run and queue its first batch. With a sync queue the whole import runs here. */
    public function start(string $path, int $batch = 100, bool $publish = true): ImportRun
    {
        $total = AgentCsv::count($path);
        AgentCsv::header($path);

        $run = ImportRun::query()->create([
            'source' => $path,
            'status' => ImportRun::STATUS_PENDING,
            'options' => ['batch' => max(1, $batch), 'publish' => $publish],
            'total_rows' => $total,
        ]);

        // a new run starts a new error file; only --resume appends to it
        if (file_exists(self::errorsPath($path))) {
            unlink(self::errorsPath($path));
        }

        if ($total === 0) {
            $run->update(['status' => ImportRun::STATUS_COMPLETED, 'started_at' => now(), 'finished_at' => now()]);

            return $run;
        }

        ImportBatch::dispatch($run->id, 1, min($run->batchSize(), $total));

        return $run->refresh();
    }

    /** Continue a running/failed run from its checkpoint. */
    public function resume(ImportRun $run): ImportRun
    {
        if ($run->isDone()) {
            return $run;
        }
        $from = $run->last_row + 1;
        if ($from > $run->total_rows) {
            $run->update(['status' => ImportRun::STATUS_COMPLETED, 'finished_at' => now()]);

            return $run;
        }
        $run->update(['status' => ImportRun::STATUS_PENDING, 'last_error' => null]);
        ImportBatch::dispatch($run->id, $from, min($from + $run->batchSize() - 1, $run->total_rows));

        return $run->refresh();
    }

    /** Process rows $from..$to, checkpoint after each row, then queue the next batch. */
    public function processBatch(ImportRun $run, int $from, int $to): void
    {
        $run->update(['status' => ImportRun::STATUS_RUNNING, 'started_at' => $run->started_at ?? now()]);

        try {
            $rows = AgentCsv::rows($run->source, $from, $to);
        } catch (Throwable $e) {
            $run->update(['status' => ImportRun::STATUS_FAILED, 'last_error' => $e->getMessage()]);

            return;
        }

        foreach ($rows as $number => $row) {
            $error = $this->importRow($row, $run->publishes());
            $run->processed_rows++;
            if ($error === null) {
                $run->created_rows++;
            } elseif ($error === 'existing') {
                $run->existing_rows++;
            } else {
                $run->error_rows++;
                $this->writeErrors($run->source, [[$number, (string) ($row['slug'] ?? ''), $error]], append: true);
                $run->errors_path = self::errorsPath($run->source);
            }
            $run->last_row = $number;
            $run->save();
        }

        if ($to >= $run->total_rows) {
            $run->update(['status' => ImportRun::STATUS_COMPLETED, 'finished_at' => now()]);

            return;
        }

        ImportBatch::dispatch($run->id, $to + 1, min($to + $run->batchSize(), $run->total_rows));
    }

    /**
     * @param  array<string, string>  $row
     * @return string|null null when a site was created, "existing" when it already existed, else the error
     */
    private function importRow(array $row, bool $publish): ?string
    {
        $problem = $this->validateRow($row);
        if ($problem !== null) {
            return $problem;
        }

        try {
            $data = AgentCsv::toData($row);
            $probe = ProvisionInput::fromArray($data, 0, $publish);
            $account = $this->accounts->resolve($probe);
            $before = Tenant::query()->where('account_id', $account->id)->count();
            $tenant = $this->provision->handle(ProvisionInput::fromArray($data, $account->id, $publish));
            $created = Tenant::query()->where('account_id', $account->id)->count() > $before;

            return $created ? null : 'existing';
        } catch (SlugUnavailable $e) {
            return 'slug '.$e->reason.': '.$e->slug.($e->suggestions !== [] ? ' (try '.implode(', ', $e->suggestions).')' : '');
        } catch (InvalidProvisionInput $e) {
            return implode('; ', $e->errors);
        } catch (Throwable $e) {
            Log::error('import.row.failed', ['row' => $row, 'error' => $e->getMessage()]);

            return 'unexpected: '.$e->getMessage();
        }
    }

    /**
     * Cheap checks that need no database: required columns, phone, theme, slug shape, locale.
     *
     * @param  array<string, string>  $row
     */
    private function validateRow(array $row): ?string
    {
        if (trim($row['name'] ?? '') === '') {
            return 'name is required';
        }
        if (trim($row['whatsapp'] ?? '') === '') {
            return 'whatsapp is required';
        }
        if ($this->phones->normalize($row['whatsapp']) === null) {
            return 'whatsapp is not a valid phone number: '.$row['whatsapp'];
        }
        if (($row['theme'] ?? '') !== '' && ! in_array($row['theme'], $this->themes->installed(), true)) {
            return 'unknown theme: '.$row['theme'];
        }
        if (($row['slug'] ?? '') !== '' && ! $this->slugs->isValidShape(strtolower($row['slug']))) {
            return 'invalid slug: '.$row['slug'];
        }
        /** @var list<string> $locales */
        $locales = config('platform.locales');
        if (($row['locale'] ?? '') !== '' && ! in_array($row['locale'], $locales, true)) {
            return 'unknown locale: '.$row['locale'];
        }

        return null;
    }

    public static function errorsPath(string $source): string
    {
        return preg_replace('/\.csv$/i', '', $source).'.errors.csv';
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $errors */
    private function writeErrors(string $source, array $errors, bool $append): string
    {
        $path = self::errorsPath($source);
        $new = $append === false || ! file_exists($path);
        $handle = fopen($path, $append ? 'a' : 'w');
        if ($handle === false) {
            throw new \RuntimeException("cannot write {$path}");
        }
        if ($new) {
            fputcsv($handle, ['row', 'slug', 'error'], escape: '');
        }
        foreach ($errors as [$number, $slug, $error]) {
            fputcsv($handle, [$number, $slug, $error], escape: '');
        }
        fclose($handle);

        return $path;
    }
}
