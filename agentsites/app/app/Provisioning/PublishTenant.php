<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Messaging\Notifier;
use App\Models\Tenant;
use App\Platform\Audit;
use App\Platform\EventLog;
use App\Provisioning\Exceptions\CannotPublish;
use App\Tenancy\HostCache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publish(tenant) — spec §12: status live, published_at, seo.noindex=false, cache purge,
 * sitemap regeneration, welcome notification, onboarding.completed. Live in ≤ 5 s, and never
 * blocked by an external API failing (§22.3).
 */
final class PublishTenant
{
    public function __construct(
        private readonly TenantConfig $config,
        private readonly HostCache $hosts,
        private readonly Notifier $notifier,
        private readonly EventLog $events,
        private readonly Audit $audit,
    ) {}

    /** @throws CannotPublish */
    public function handle(Tenant $tenant): Tenant
    {
        $missing = self::missingForPublish($tenant);
        if ($missing !== []) {
            throw new CannotPublish($missing);
        }

        $firstPublish = $tenant->published_at === null;

        /** @var array<string, mixed> $stored */
        $stored = $tenant->config ?? [];
        data_set($stored, 'seo.noindex', false);

        $tenant->status = Tenant::STATUS_LIVE;
        $tenant->published_at ??= now();
        $tenant->onboarding_completed_at ??= now();
        $this->config->save($tenant, $stored);

        $this->hosts->forgetTenant($tenant);
        // Sitemap regeneration hooks in with the page cache (Phase 3); pages are rendered on demand.

        $this->audit->record('tenant.published', ['first' => $firstPublish], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
        $this->events->record('published', ['first' => $firstPublish], tenant: $tenant, account: $tenant->account);
        if ($firstPublish) {
            $this->events->record('onboarding.completed', [], tenant: $tenant, account: $tenant->account);
            $this->welcome($tenant);
        }

        return $tenant;
    }

    /**
     * What a draft still lacks before it can go live: a real name and a WhatsApp number
     * (an onboarding draft may have neither yet — spec §13 S1/S2).
     *
     * @return list<string>
     */
    public static function missingForPublish(Tenant $tenant): array
    {
        $config = $tenant->mergedConfig();
        $missing = [];
        if (mb_strlen(trim((string) data_get($config, 'identity.display_name', ''))) < 2) {
            $missing[] = 'identity.display_name';
        }
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', (string) data_get($config, 'contact.whatsapp', '')) !== 1) {
            $missing[] = 'contact.whatsapp';
        }

        return $missing;
    }

    private function welcome(Tenant $tenant): void
    {
        $to = (string) data_get($tenant->config, 'contact.whatsapp', '');
        if ($to === '') {
            return;
        }
        try {
            $result = $this->notifier->whatsapp($to, __('platform.success.share_text', ['url' => $tenant->url()], $tenant->defaultLocale()));
            if (! $result->accepted) {
                Log::warning('welcome.whatsapp.failed', ['tenant_id' => $tenant->id, 'error' => $result->error]);
            }
        } catch (Throwable $e) {
            Log::warning('welcome.whatsapp.exception', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }
}
