<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Tenant;

/** @extends TenantScopedFactory<Domain> */
class DomainFactory extends TenantScopedFactory
{
    protected $model = Domain::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'host' => fake()->unique()->domainWord().'-'.fake()->numerify('###').'.example',
            'type' => Domain::TYPE_CUSTOM,
            'role' => Domain::ROLE_PRIMARY,
            'verified' => true,
            'dns_status' => 'verified',
            'ssl_status' => 'issued',
        ];
    }

    public function alias(): static
    {
        return $this->state(fn (): array => ['role' => Domain::ROLE_ALIAS]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['verified' => false, 'dns_status' => 'pending', 'ssl_status' => 'none']);
    }
}
