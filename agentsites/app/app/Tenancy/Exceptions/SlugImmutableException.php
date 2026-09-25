<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

/** The slug of a published tenant may only change through platform:site:rename (spec §8). */
final class SlugImmutableException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('Slug "%s" is immutable after publish; use platform:site:rename, which writes the redirect rule.', $slug));
    }
}
