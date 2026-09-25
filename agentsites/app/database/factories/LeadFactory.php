<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Tenant;

/** @extends TenantScopedFactory<Lead> */
class LeadFactory extends TenantScopedFactory
{
    protected $model = Lead::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => 'form',
            'name' => fake()->name(),
            'phone' => '+9715'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'message' => fake()->sentence(8),
            'status' => 'new',
        ];
    }
}
