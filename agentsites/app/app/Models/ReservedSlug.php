<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Runtime additions to config/reserved_slugs.php. Spec §8 — 019. */
#[Fillable(['slug', 'reason'])]
class ReservedSlug extends Model {}
