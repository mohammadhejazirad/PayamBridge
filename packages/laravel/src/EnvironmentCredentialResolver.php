<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use Illuminate\Support\Env;
use InvalidArgumentException;
use PayamBridge\Security\Credential;

final class EnvironmentCredentialResolver implements CredentialResolver
{
    public function resolve(string $reference): Credential
    {
        if (! preg_match('/\A[A-Z][A-Z0-9_]*\z/D', $reference)) {
            throw new InvalidArgumentException('Credential reference must be an environment variable name.');
        }
        $value = Env::get($reference);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('A required PayamBridge credential is missing.');
        }

        return new Credential($value);
    }
}
