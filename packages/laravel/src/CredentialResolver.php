<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use PayamBridge\Security\Credential;

interface CredentialResolver
{
    public function resolve(string $reference): Credential;
}
