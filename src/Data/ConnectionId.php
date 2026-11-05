<?php

declare(strict_types=1);

namespace PayamBridge\Data;

use InvalidArgumentException;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;

final readonly class ConnectionId
{
    public function __construct(
        public Provider $provider,
        public Surface $surface,
        public string $name,
        public string $tenant = 'default',
    ) {
        foreach ([$name, $tenant] as $part) {
            if (! preg_match('/\A[a-zA-Z0-9_.-]{1,128}\z/D', $part)) {
                throw new InvalidArgumentException('Connection and tenant names must be 1–128 ASCII letters, digits, dots, hyphens or underscores.');
            }
        }
    }

    public function key(): string
    {
        return implode(':', [$this->provider->value, $this->surface->value, $this->tenant, $this->name]);
    }

    public function equals(self $other): bool
    {
        return $this->key() === $other->key();
    }
}
