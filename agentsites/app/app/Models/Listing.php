<?php

declare(strict_types=1);

namespace App\Models;

use App\Caching\PurgesSitePages;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A property listing. Spec §8 — 008. */
#[Fillable(['tenant_id', 'ref', 'title_en', 'title_ar', 'description_en', 'description_ar', 'offering', 'property_type', 'price', 'currency', 'bedrooms', 'bathrooms', 'area_sqft', 'community', 'city', 'lat', 'lng', 'status', 'source', 'feed_id', 'feed_ref', 'featured', 'media'])]
class Listing extends Model
{
    use BelongsToTenant, PurgesSitePages;

    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    public const SOURCE_DEMO = 'demo';

    public const STATUS_AVAILABLE = 'available';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'area_sqft' => 'decimal:2',
            'lat' => 'float',
            'lng' => 'float',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'featured' => 'boolean',
            'media' => 'array',
        ];
    }

    /** @return BelongsTo<ListingFeed, $this> */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(ListingFeed::class);
    }

    /** @param  Builder<Listing>  $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE);
    }

    /** @param  Builder<Listing>  $query */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('featured', true);
    }

    /** @param  Builder<Listing>  $query */
    public function scopeReal(Builder $query): void
    {
        $query->where('source', '!=', self::SOURCE_DEMO);
    }

    public function isDemo(): bool
    {
        return $this->source === self::SOURCE_DEMO;
    }

    public function title(string $locale): string
    {
        $ar = (string) ($this->title_ar ?? '');

        return $locale === 'ar' && $ar !== '' ? $ar : (string) $this->title_en;
    }

    public function description(string $locale): string
    {
        $ar = (string) ($this->description_ar ?? '');

        return $locale === 'ar' && $ar !== '' ? $ar : (string) ($this->description_en ?? '');
    }

    /**
     * Gallery URLs (the hero variant when the photo was processed, else the stored URL).
     *
     * @return list<string>
     */
    public function images(): array
    {
        return array_values(array_filter(array_map(static fn (array $set): string => $set['hero'], $this->imageSets())));
    }

    /**
     * thumb / card / hero per photo (spec §16). Plain URL entries (demo, CSV, feeds whose media
     * could not be cached) use the same URL for every size.
     *
     * @return list<array{thumb: string, card: string, hero: string}>
     */
    public function imageSets(): array
    {
        /** @var array<int, mixed> $media */
        $media = $this->getAttribute('media') ?? [];
        $sets = [];
        foreach ($media as $item) {
            if (is_array($item)) {
                $variants = (array) ($item['variants'] ?? []);
                $fallback = (string) ($item['path'] ?? $item['url'] ?? $item['remote'] ?? '');
                $hero = (string) ($variants['hero'] ?? $fallback);
                if ($hero === '') {
                    continue;
                }
                $sets[] = ['thumb' => (string) ($variants['thumb'] ?? $hero), 'card' => (string) ($variants['card'] ?? $hero), 'hero' => $hero];
            } elseif (is_string($item) && $item !== '') {
                $sets[] = ['thumb' => $item, 'card' => $item, 'hero' => $item];
            }
        }

        return $sets;
    }

    public function coverImage(): ?string
    {
        return $this->imageSets()[0]['card'] ?? null;
    }
}
