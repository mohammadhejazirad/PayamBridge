<?php

declare(strict_types=1);

namespace PayamBridge\Security;

use InvalidArgumentException;
use LogicException;
use SensitiveParameter;
use SensitiveParameterValue;

final readonly class Credential
{
    private SensitiveParameterValue $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        // Tokens are inserted into fixed URL path segments or JSON, never accepted as a URL.
        if (! preg_match('/\A[A-Za-z0-9_:-]+\z/D', $value)) {
            throw new InvalidArgumentException('A nonempty credential containing only letters, digits, underscores, colons and hyphens is required.');
        }
        // Engine-provided opaque storage also protects var_export(), which does
        // not honor __debugInfo() on an ordinary private string property.
        $this->value = new SensitiveParameterValue($value);
    }

    public function reveal(): string
    {
        return $this->value->getValue();
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new LogicException('Credentials must not be serialized. Resolve them inside the worker.');
    }
}
