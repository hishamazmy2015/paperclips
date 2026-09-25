<?php

declare(strict_types=1);

namespace App\Provisioning\Exceptions;

use InvalidArgumentException;

final class InvalidTenantConfig extends InvalidArgumentException
{
    /** @param  list<string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Tenant config does not match the schema: '.implode('; ', $errors));
    }
}
