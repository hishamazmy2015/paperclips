<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use App\Tenancy\Exceptions\MissingTenantContextException;
use App\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every model that has a tenant_id column (spec §8 invariants):
 *  - reads are constrained to the bound tenant (TenantScope);
 *  - creates take tenant_id from the context and refuse another tenant's id;
 *  - updates can never move a row to another tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $given = $model->getAttribute('tenant_id');

            if ($context->has()) {
                if ($given === null) {
                    $model->setAttribute('tenant_id', $context->id());
                } elseif ((int) $given !== $context->id()) {
                    throw new TenantMismatchException((int) $given, (int) $context->id());
                }

                return;
            }

            if (! $context->isGlobalAllowed()) {
                throw new MissingTenantContextException(static::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new TenantMismatchException((int) $model->getAttribute('tenant_id'), (int) $model->getOriginal('tenant_id'));
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query across tenants. Platform code only (resolver, admin, purge); every call site
     * is an explicit, reviewable decision.
     *
     * @return Builder<static>
     */
    public static function unscopedByTenant(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
