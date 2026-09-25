<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;

/** platform:site:list [--status=live] [--limit=50] — slug, host, status, name, theme, locale per site. */
final class SiteListCommand extends PlatformCommand
{
    protected $signature = 'platform:site:list {--status= : draft|live|suspended} {--limit=50 : 0 for all} {--random : Random order}';

    protected $description = 'List sites (slug, host, status, display name, theme, locale)';

    public function handle(): int
    {
        $query = Tenant::query();
        $status = (string) $this->option('status');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ((bool) $this->option('random')) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('id');
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $sites = $query->get()->map(fn (Tenant $tenant): array => [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'host' => $tenant->subdomainHost(),
            'status' => $tenant->status,
            'name' => $tenant->displayName(),
            'theme' => $tenant->theme_key,
            'locale' => $tenant->defaultLocale(),
            'palette' => (string) data_get($tenant->config, 'branding.palette', 'sand'),
        ])->all();

        return $this->emit(['ok' => true, 'count' => count($sites), 'sites' => $sites]);
    }
}
