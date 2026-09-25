<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\Tenant;

/** @extends TenantScopedFactory<Media> */
class MediaFactory extends TenantScopedFactory
{
    protected $model = Media::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'disk' => 'media',
            'path' => 'uploads/'.fake()->unique()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(20_000, 900_000),
            'width' => 1600,
            'height' => 1200,
            'variants' => [],
        ];
    }
}
