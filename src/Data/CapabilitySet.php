<?php

declare(strict_types=1);

namespace PayamBridge\Data;

use PayamBridge\Contracts\Capability;
use PayamBridge\Exceptions\UnsupportedCapabilityException;

final readonly class CapabilitySet
{
    /** @var list<Capability> */
    private array $capabilities;

    public function __construct(Capability ...$capabilities)
    {
        $this->capabilities = array_values(array_unique($capabilities, SORT_REGULAR));
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function require(Capability $capability): void
    {
        if (! $this->supports($capability)) {
            throw new UnsupportedCapabilityException($capability);
        }
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(static fn (Capability $capability): string => $capability->value, $this->capabilities);
    }
}
