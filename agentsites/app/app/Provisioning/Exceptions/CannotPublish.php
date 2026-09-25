<?php

declare(strict_types=1);

namespace App\Provisioning\Exceptions;

use RuntimeException;

/** A draft cannot go live yet: it still lacks the facts PublishTenant::missingForPublish lists. */
final class CannotPublish extends RuntimeException
{
    /** @param  list<string>  $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('Cannot publish: missing '.implode(', ', $missing));
    }
}
