<?php

declare(strict_types=1);

namespace App\Models;

use App\Caching\PurgesSitePages;
use App\Tenancy\BelongsToTenant;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A host that serves a tenant: its {slug}.{base} subdomain or a custom domain the agent
 * owns. Spec §8 — 005. Hosts are stored lowercase and are unique platform-wide.
 */
#[Fillable(['tenant_id', 'host', 'type', 'role', 'verified', 'verification_token', 'dns_status', 'ssl_status', 'last_checked_at', 'last_error'])]
class Domain extends Model
{
    use BelongsToTenant, PurgesSitePages;

    /** @use HasFactory<DomainFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_SUBDOMAIN = 'subdomain';

    public const TYPE_CUSTOM = 'custom';

    public const ROLE_PRIMARY = 'primary';

    public const ROLE_ALIAS = 'alias';

    protected static function booted(): void
    {
        static::saving(function (Domain $domain): void {
            $domain->host = strtolower(trim((string) $domain->host));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /** @param  Builder<Domain>  $query */
    public function scopePrimary(Builder $query): void
    {
        $query->where('role', self::ROLE_PRIMARY);
    }

    public function isPrimary(): bool
    {
        return $this->role === self::ROLE_PRIMARY;
    }
}
