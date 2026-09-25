<?php

declare(strict_types=1);

namespace App\Provisioning\Exceptions;

use InvalidArgumentException;

final class InvalidProvisionInput extends InvalidArgumentException
{
    /** @param  list<string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid provisioning input: '.implode('; ', $errors));
    }
}
