<?php

namespace App\Exceptions;

use RuntimeException;

class TenantDomainsRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Assign frontend and backend domains for this customer in SoftKatta Admin → Tenants before generating a license or running project setup.')
    {
        parent::__construct($message);
    }
}
