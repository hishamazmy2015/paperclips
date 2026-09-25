<?php

declare(strict_types=1);

namespace App\Models;

use App\Platform\Hosts;
use App\Provisioning\TenantConfig;
use App\Tenancy\Exceptions\SlugImmutableException;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One website. Everything about it is data: creating, editing, cloning, suspending or
 * regenerating a tenant never needs a deploy (goal G1). Spec §8 — 004.
 *
 * @property array<string, mixed>|null $config
 * @property array<string, string>|null $ai_generated_fields
 * @property int $onboarding_step
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $reminder_1h_sent_at
 * @property Carbon|null $reminder_24h_sent_at
 */
#[Fillable(['account_id', 'slug', 'status', 'theme_key', 'config', 'config_version', 'published_at', 'onboarding_step', 'onboarding_completed_at', 'ai_generated_fields', 'reminder_1h_sent_at', 'reminder_24h_sent_at'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DELETED = 'deleted';

    /** Set by the rename service around the one legitimate slug change (spec §8 invariant). */
    private static bool $renaming = false;

    /** Primary host known from the host cache: rendering then needs no domains query (§11 budget). */
    private ?string $resolvedPrimaryHost = null;

    protected static function booted(): void
    {
        static::updating(function (Tenant $tenant): void {
            if ($tenant->isDirty('slug') && $tenant->published_at !== null && ! self::$renaming) {
                throw new SlugImmutableException($tenant->getOriginal('slug'));
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'ai_generated_fields' => 'array',
            'config_version' => 'integer',
            'onboarding_step' => 'integer',
            'published_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
        ];
    }

    /** Run $fn with slug changes permitted — only platform:site:rename may use this. */
    public static function whileRenaming(callable $fn): mixed
    {
        self::$renaming = true;
        try {
            return $fn();
        } finally {
            self::$renaming = false;
        }
    }

    // ── relations ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return HasMany<Testimonial, $this> */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<ListingFeed, $this> */
    public function feeds(): HasMany
    {
        return $this->hasMany(ListingFeed::class);
    }

    /** @return HasMany<SiteStatsDaily, $this> */
    public function stats(): HasMany
    {
        return $this->hasMany(SiteStatsDaily::class);
    }

    /** @return HasMany<AbuseReport, $this> */
    public function abuseReports(): HasMany
    {
        return $this->hasMany(AbuseReport::class);
    }

    // ── scopes ────────────────────────────────────────────────────────────

    /** @param  Builder<Tenant>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', self::STATUS_LIVE);
    }

    // ── hosts ─────────────────────────────────────────────────────────────

    /** {slug}.{base}, derived at call time from the configured base domain (spec §4). */
    public function subdomainHost(): string
    {
        return Hosts::tenant($this->slug);
    }

    public function primaryDomain(): ?Domain
    {
        return Domain::unscopedByTenant()
            ->where('tenant_id', $this->id)
            ->where('role', Domain::ROLE_PRIMARY)
            ->orderBy('id')
            ->first();
    }

    public function primaryHost(): string
    {
        return $this->resolvedPrimaryHost ?? $this->primaryDomain()->host ?? $this->subdomainHost();
    }

    /** Called by the resolver middleware with the cached primary host. */
    public function useResolvedPrimaryHost(string $host): void
    {
        $this->resolvedPrimaryHost = $host;
    }

    public function url(string $path = '/'): string
    {
        return Hosts::url($this->primaryHost(), $path);
    }

    // ── status ────────────────────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    // ── config ────────────────────────────────────────────────────────────

    /**
     * Stored config with the platform defaults merged in (spec §9: defaults are merged at
     * read time, never stored).
     *
     * @return array<string, mixed>
     */
    public function mergedConfig(): array
    {
        return app(TenantConfig::class)->merged($this);
    }

    public function displayName(): string
    {
        $config = $this->config ?? [];

        return (string) ($config['identity']['display_name'] ?? $this->slug);
    }

    public function defaultLocale(): string
    {
        $config = $this->config ?? [];

        return (string) ($config['locale']['default'] ?? config('platform.default_locale'));
    }

    /** Owner-only preview of a draft site (spec §11 step 5). */
    public function previewToken(): string
    {
        return hash_hmac('sha256', 'preview:'.$this->id, (string) config('app.key'));
    }
}
