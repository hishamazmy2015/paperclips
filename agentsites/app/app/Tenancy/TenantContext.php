<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * The one active tenant for the current request, job or command (spec §8 invariants).
 * Tenant-scoped models refuse to run a query unless a tenant is bound here or global
 * (cross-tenant) access has been explicitly allowed around a block of system code.
 *
 * Registered as a container singleton; use the static helpers from application code.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private int $globalDepth = 0;

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    /** Bind $tenant while $fn runs, then restore whatever was bound before. */
    public function run(Tenant $tenant, callable $fn): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        try {
            return $fn($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }

    /**
     * Allow unscoped (all-tenant) queries while $fn runs. For platform code only: the
     * resolver, provisioning, admin commands, purge jobs. Never call this from a request
     * handler that serves one tenant.
     */
    public function allowGlobal(callable $fn): mixed
    {
        $this->globalDepth++;
        try {
            return $fn();
        } finally {
            $this->globalDepth--;
        }
    }

    public function isGlobalAllowed(): bool
    {
        return $this->globalDepth > 0;
    }

    // ── static sugar ──────────────────────────────────────────────────────

    public static function instance(): self
    {
        return app(self::class);
    }

    public static function current(): ?Tenant
    {
        return self::instance()->tenant();
    }

    public static function currentId(): ?int
    {
        return self::instance()->id();
    }

    /** @throws Exceptions\MissingTenantContextException */
    public static function require(): Tenant
    {
        return self::instance()->tenant() ?? throw new Exceptions\MissingTenantContextException('request');
    }

    public static function with(Tenant $tenant, callable $fn): mixed
    {
        return self::instance()->run($tenant, $fn);
    }

    public static function global(callable $fn): mixed
    {
        return self::instance()->allowGlobal($fn);
    }
}
