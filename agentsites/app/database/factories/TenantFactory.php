<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->firstName().' '.fake()->lastName();

        return [
            'account_id' => Account::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'status' => Tenant::STATUS_DRAFT,
            'theme_key' => 'atlas',
            'config' => [
                'identity' => ['display_name' => $name],
                'contact' => ['whatsapp' => '+9715'.fake()->numerify('########')],
            ],
            'config_version' => 1,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (): array => ['status' => Tenant::STATUS_LIVE, 'published_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => Tenant::STATUS_SUSPENDED, 'published_at' => now()]);
    }

    /** @param  array<string, mixed>  $config */
    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes): array => [
            'config' => array_replace_recursive($attributes['config'], $config),
        ]);
    }
}
