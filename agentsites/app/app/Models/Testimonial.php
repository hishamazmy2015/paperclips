<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Spec §8 — 010. */
#[Fillable(['tenant_id', 'author_name', 'author_role', 'text_en', 'text_ar', 'rating', 'photo', 'featured', 'sort_order'])]
class Testimonial extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TestimonialFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function text(string $locale): string
    {
        $ar = (string) ($this->text_ar ?? '');

        return $locale === 'ar' && $ar !== '' ? $ar : (string) ($this->text_en ?? '');
    }
}
