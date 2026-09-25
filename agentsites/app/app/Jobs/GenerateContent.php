<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\ContentGenerator;
use App\Models\Tenant;
use App\Provisioning\TenantConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Spec §12.3: replace template-generated copy with the bound ContentGenerator's output (the
 * Claude generator in Phase 2). Fields the agent typed are never touched; failures are logged
 * and leave the template text in place — publishing never waits for this (§22.3).
 */
final class GenerateContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $tenantId)
    {
        $this->afterCommit();
    }

    public function handle(ContentGenerator $generator, TenantConfig $config): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $marks = $tenant->ai_generated_fields ?? [];
        $fields = [];
        foreach (array_keys($marks) as $key) {
            if ($marks[$key] === 'template' && str_contains((string) $key, '.')) {
                $parts = explode('.', (string) $key);
                $locale = array_pop($parts);
                if (in_array($locale, ['ar', 'en'], true)) {
                    $fields[implode('.', $parts)][] = $locale;
                }
            }
        }
        if ($fields === []) {
            return;
        }

        try {
            /** @var list<string> $locales */
            $locales = array_values(array_unique(array_merge(...array_values($fields))));
            $generated = $generator->generate($tenant->mergedConfig(), array_keys($fields), $locales);
        } catch (Throwable $e) {
            Log::warning('content.generate.failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return;
        }

        /** @var array<string, mixed> $stored */
        $stored = $tenant->config ?? [];
        foreach ($generated as $field => $texts) {
            foreach ($texts as $locale => $text) {
                if (($marks["{$field}.{$locale}"] ?? null) === 'template' && trim($text) !== '') {
                    data_set($stored, "{$field}.{$locale}", $text);
                    $marks["{$field}.{$locale}"] = 'ai';
                }
            }
        }
        $tenant->ai_generated_fields = $marks;
        $config->save($tenant, $stored);
    }
}
