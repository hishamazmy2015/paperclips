<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Tenant;

/** @extends TenantScopedFactory<Listing> */
class ListingFactory extends TenantScopedFactory
{
    protected $model = Listing::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $community = fake()->randomElement(['Downtown', 'Marina', 'Business Bay', 'JVC', 'Dubai Hills']);

        return [
            'tenant_id' => Tenant::factory(),
            'ref' => 'REF-'.fake()->unique()->numerify('#####'),
            'title_en' => fake()->numberBetween(1, 4).'BR apartment in '.$community,
            'title_ar' => 'شقة في '.$community,
            'description_en' => fake()->sentence(12),
            'description_ar' => 'وصف العقار',
            'offering' => 'sale',
            'property_type' => 'apartment',
            'price' => fake()->numberBetween(500, 9000) * 1000,
            'currency' => 'AED',
            'bedrooms' => fake()->numberBetween(1, 4),
            'bathrooms' => fake()->numberBetween(1, 4),
            'area_sqft' => fake()->numberBetween(500, 4000),
            'community' => $community,
            'city' => 'Dubai',
            'status' => Listing::STATUS_AVAILABLE,
            'source' => 'manual',
            'featured' => false,
            'media' => [],
        ];
    }

    public function demo(): static
    {
        return $this->state(fn (): array => ['source' => Listing::SOURCE_DEMO]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['featured' => true]);
    }
}
