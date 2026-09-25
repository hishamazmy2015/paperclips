<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Tenancy\Exceptions\MissingTenantContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope on every tenant-scoped model (spec §8): constrains every query to the bound
 * tenant, and throws when nothing is bound and global access was not explicitly allowed.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /** @param  Builder<covariant Model>  $builder */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->id());

            return;
        }

        if ($context->isGlobalAllowed()) {
            return;
        }

        throw new MissingTenantContextException($model::class);
    }
}
