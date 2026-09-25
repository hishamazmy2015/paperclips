<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base factory for tenant-scoped models: tests create fixtures for many tenants, so the
 * insert runs with global access allowed (the model still requires an explicit tenant_id).
 *
 * @template TModel of Model
 *
 * @extends Factory<TModel>
 */
abstract class TenantScopedFactory extends Factory
{
    public function create($attributes = [], ?Model $parent = null)
    {
        return app(TenantContext::class)->allowGlobal(fn () => parent::create($attributes, $parent));
    }
}
