<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Domain;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantContext;

/** platform:site:export <slug> — the tenant's data as JSON (spec §15; the agent's data-export zip builds on it). */
final class SiteExportCommand extends PlatformCommand
{
    protected $signature = 'platform:site:export {slug} {--out= : Write to this file instead of stdout}';

    protected $description = 'Export a site (config, domains, listings, testimonials, leads) as JSON';

    public function handle(): int
    {
        $tenant = $this->findTenant((string) $this->argument('slug'));
        if ($tenant === null) {
            return $this->failWith("no tenant with slug '{$this->argument('slug')}'");
        }

        $export = TenantContext::with($tenant, fn (Tenant $tenant): array => [
            'exported_at' => now()->toIso8601String(),
            'tenant' => [
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'theme_key' => $tenant->theme_key,
                'config' => $tenant->config,
                'config_version' => $tenant->config_version,
                'published_at' => $tenant->getAttribute('published_at')?->toIso8601String(),
            ],
            'domains' => Domain::query()->get(['host', 'type', 'role', 'verified', 'dns_status', 'ssl_status'])->toArray(),
            'listings' => Listing::query()->real()->get()->makeHidden(['id', 'tenant_id', 'feed_id', 'deleted_at'])->toArray(),
            'testimonials' => Testimonial::query()->get()->makeHidden(['id', 'tenant_id', 'deleted_at'])->toArray(),
            'leads' => Lead::query()->get()->makeHidden(['id', 'tenant_id', 'deleted_at'])->toArray(),
        ]);

        $json = (string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $out = (string) $this->option('out');
        if ($out !== '') {
            file_put_contents($out, $json);

            return $this->emit(['ok' => true, 'file' => $out, 'bytes' => strlen($json)], "Exported {$tenant->slug} → {$out}");
        }
        $this->line($json);

        return self::SUCCESS;
    }
}
