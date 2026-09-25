<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An uploaded file under {media_root}/{tenant_id}/ with its generated variants. Spec §8 — 011. */
#[Fillable(['tenant_id', 'disk', 'path', 'mime', 'size_bytes', 'width', 'height', 'variants', 'alt_en', 'alt_ar', 'sha256'])]
class Media extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }
}
