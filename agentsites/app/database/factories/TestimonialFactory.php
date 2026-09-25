<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Testimonial;

/** @extends TenantScopedFactory<Testimonial> */
class TestimonialFactory extends TenantScopedFactory
{
    protected $model = Testimonial::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'author_name' => fake()->name(),
            'author_role' => 'Buyer',
            'text_en' => fake()->sentence(10),
            'text_ar' => 'خدمة ممتازة وسريعة.',
            'rating' => 5,
            'featured' => true,
            'sort_order' => 0,
        ];
    }
}
