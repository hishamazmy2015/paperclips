<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Content\ContentGenerator;
use App\Content\TemplateGenerator;
use App\Jobs\GenerateContent;
use App\Jobs\SeedDemoListings;
use App\Jobs\WarmCache;
use App\Models\Account;
use App\Models\Domain;
use App\Models\Tenant;
use App\Platform\Audit;
use App\Platform\EventLog;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Tenancy\TenantContext;
use App\Themes\ThemeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single write path for creating a site (spec §12, goal G1). Creating a tenant is a data
 * change: no deploy, no config file, no restart. Idempotent on (account_id, slug).
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly TenantConfig $config,
        private readonly Slugs $slugs,
        private readonly ContentGenerator $generator,
        private readonly TemplateGenerator $fallback,
        private readonly ThemeRegistry $themes,
        private readonly PhoneNormalizer $phones,
        private readonly EventLog $events,
        private readonly Audit $audit,
        private readonly PublishTenant $publisher,
    ) {}

    /**
     * @throws InvalidProvisionInput
     * @throws Exceptions\SlugUnavailable
     */
    public function handle(ProvisionInput $in): Tenant
    {
        $account = Account::query()->findOrFail($in->accountId);
        [$config, $theme, $locale] = $this->validate($in);

        // 2. one transaction: slug, tenant, subdomain host, audit, event (§12.2)
        $tenant = DB::transaction(function () use ($in, $account, $config, $theme, $locale): Tenant {
            $existing = $this->existing($account, $in, $config);
            if ($existing !== null) {
                Log::info('provision.idempotent', ['tenant_id' => $existing->id, 'slug' => $existing->slug]);

                return $existing;
            }

            $slug = $this->slugs->reserve($in->slug, (string) $config['identity']['display_name']);
            $config['_schema'] = TenantConfig::SCHEMA_VERSION;

            $tenant = Tenant::query()->create([
                'account_id' => $account->id,
                'slug' => $slug,
                'status' => Tenant::STATUS_DRAFT,
                'theme_key' => $theme,
                'config' => $config,
                'config_version' => 1,
            ]);

            TenantContext::with($tenant, function (Tenant $tenant): void {
                Domain::query()->create([
                    'host' => $tenant->subdomainHost(),
                    'type' => Domain::TYPE_SUBDOMAIN,
                    'role' => Domain::ROLE_PRIMARY,
                    'verified' => true,
                    'dns_status' => 'verified',
                    'ssl_status' => 'issued', // covered by the wildcard certificate (§11)
                ]);
            });

            $this->audit->record('tenant.created', ['slug' => $slug, 'theme' => $theme, 'locale' => $locale], tenant: $tenant, account: $account, targetType: Tenant::class, targetId: $tenant->id);
            $this->events->record('tenant.created', ['theme' => $theme, 'locale' => $locale], tenant: $tenant, account: $account);

            return $tenant;
        });

        // 3. after commit: content (never blank), demo listings, cache warm-up (§12.3)
        $this->fillMissingContent($tenant);
        if (! $this->generator instanceof TemplateGenerator) {
            GenerateContent::dispatch($tenant->id);
        }
        SeedDemoListings::dispatch($tenant->id);
        WarmCache::dispatch($tenant->id);

        if ($in->publish && ! $tenant->isLive()) {
            $tenant = $this->publisher->handle($tenant);
        }

        return $tenant->refresh();
    }

    /**
     * Validate + normalise (§12.1): phone to E.164 (UAE default), name, theme, locale, and the
     * assembled config against the JSON schema.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function validate(ProvisionInput $in): array
    {
        $errors = [];

        $name = trim($in->name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'name: 2 to 80 characters required';
        }

        $whatsapp = $this->phones->normalize($in->whatsapp);
        if ($whatsapp === null) {
            $errors[] = 'whatsapp: not a valid phone number (E.164, e.g. +9715XXXXXXXX)';
        }

        $theme = $in->themeKey ?? $this->themes->default();
        if (! $this->themes->has($theme)) {
            $errors[] = "theme: unknown theme '{$theme}'";
        }

        $locale = $in->locale ?? (string) config('platform.default_locale');
        /** @var list<string> $locales */
        $locales = config('platform.locales');
        if (! in_array($locale, $locales, true)) {
            $errors[] = 'locale: must be one of '.implode(', ', $locales);
        }

        if ($in->slug !== null && ! $this->slugs->isValidShape(strtolower($in->slug))) {
            $errors[] = 'slug: lowercase letters, digits and hyphens, 3–40 characters';
        }

        if ($errors !== []) {
            throw new InvalidProvisionInput($errors);
        }

        /** @var array<string, mixed> $config */
        $config = $in->config;
        unset($config['_schema']);
        $scalars = [
            'identity' => ['display_name' => $name, 'agency_name' => $in->agency, 'license_no' => $in->license, 'brn' => $in->brn],
            'contact' => ['whatsapp' => $whatsapp, 'email' => $in->email],
            'content' => ['service_areas' => $in->areas !== [] ? $in->areas : null],
            'locale' => ['default' => $locale],
        ];
        $config = TenantConfig::mergeDefaults($config, $this->config->normalize($scalars));
        $config = $this->config->normalize($config);

        $schemaErrors = $this->config->validate($config);
        if ($schemaErrors !== []) {
            throw new InvalidProvisionInput($schemaErrors);
        }

        return [$config, $theme, $locale];
    }

    /**
     * Idempotency key (account_id, slug): a retry of the same request returns the tenant it
     * already created (§12.4).
     *
     * @param  array<string, mixed>  $config
     */
    private function existing(Account $account, ProvisionInput $in, array $config): ?Tenant
    {
        $slug = $in->slug !== null ? strtolower(trim($in->slug)) : Slugs::fromName((string) $config['identity']['display_name']);

        return Tenant::query()->where('account_id', $account->id)->where('slug', $slug)->first();
    }

    /**
     * Fill tagline/bio/about/why_me/meta in every enabled locale when missing. The template
     * generator runs synchronously so the site is never blank (§12.3, §22.3).
     */
    public function fillMissingContent(Tenant $tenant): void
    {
        /** @var array<string, mixed> $stored */
        $stored = $tenant->config ?? [];
        /** @var list<string> $locales */
        $locales = $stored['locale']['enabled'] ?? config('platform.locales');
        $merged = $tenant->mergedConfig();

        $fields = ['identity.tagline', 'identity.bio', 'content.about', 'seo.meta_description'];
        $missing = [];
        foreach ($fields as $field) {
            foreach ($locales as $locale) {
                if (trim((string) data_get($stored, "{$field}.{$locale}", '')) === '') {
                    $missing[$field] = true;
                }
            }
        }

        $generated = [];
        if ($missing !== []) {
            $generated = $this->fallback->generate($merged, array_keys($missing), $locales);
        }

        $changed = false;
        $marks = $tenant->ai_generated_fields ?? [];
        foreach ($generated as $field => $texts) {
            foreach ($texts as $locale => $text) {
                if (trim((string) data_get($stored, "{$field}.{$locale}", '')) === '' && $text !== '') {
                    data_set($stored, "{$field}.{$locale}", $text);
                    $marks["{$field}.{$locale}"] = 'template';
                    $changed = true;
                }
            }
        }
        if (empty($stored['content']['why_me'])) {
            data_set($stored, 'content.why_me', $this->fallback->whyMe($merged));
            $marks['content.why_me'] = 'template';
            $changed = true;
        }

        if ($changed) {
            $tenant->ai_generated_fields = $marks;
            $this->config->save($tenant, $stored);
        }
    }
}
