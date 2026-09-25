<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base for platform:* commands (spec §15): idempotent, --json output, non-zero exit on error
 * with the error as JSON when --json is on.
 */
abstract class PlatformCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Machine-readable output');
    }

    /** @param  array<string, mixed>  $data */
    protected function emit(array $data, string $human = ''): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } elseif ($human !== '') {
            $this->info($human);
        } else {
            foreach ($data as $key => $value) {
                $this->line(sprintf('%-16s %s', $key.':', is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
            }
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $extra */
    protected function failWith(string $message, array $extra = []): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(['ok' => false, 'error' => $message] + $extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($message);
            foreach ($extra as $key => $value) {
                $this->line('  '.$key.': '.(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
            }
        }

        return self::FAILURE;
    }

    protected function findTenant(string $slug, bool $withTrashed = false): ?Tenant
    {
        $query = $withTrashed ? Tenant::withTrashed() : Tenant::query();

        return $query->where('slug', strtolower(trim($slug)))->first();
    }

    /** @return array<string, mixed> */
    protected function describe(Tenant $tenant, bool $created = true): array
    {
        return [
            'ok' => true,
            'created' => $created,
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'theme' => $tenant->theme_key,
            'account_id' => $tenant->account_id,
            'host' => $tenant->primaryHost(),
            'url' => $tenant->url(),
            'preview_url' => $tenant->isDraft() ? $tenant->url('/?preview='.$tenant->previewToken()) : null,
            'published_at' => $tenant->getAttribute('published_at')?->toIso8601String(),
        ];
    }
}
