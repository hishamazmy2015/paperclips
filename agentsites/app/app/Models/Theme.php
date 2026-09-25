<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Installed theme record (mirror of config/themes.php + manifest). Spec §8 — 007. */
#[Fillable(['key', 'name', 'version', 'preview_path', 'manifest', 'active'])]
class Theme extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'active' => 'boolean',
        ];
    }
}
