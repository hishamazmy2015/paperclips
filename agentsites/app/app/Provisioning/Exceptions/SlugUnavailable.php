<?php

declare(strict_types=1);

namespace App\Provisioning\Exceptions;

use RuntimeException;

final class SlugUnavailable extends RuntimeException
{
    /** @param  list<string>  $suggestions */
    public function __construct(public readonly string $slug, public readonly string $reason, public readonly array $suggestions = [])
    {
        parent::__construct(sprintf('Slug "%s" is %s%s', $slug, $reason, $suggestions !== [] ? '; try: '.implode(', ', $suggestions) : ''));
    }
}
