<?php

declare(strict_types=1);

namespace PayamBridge\Http;

use InvalidArgumentException;

final readonly class Timeouts
{
    public function __construct(public float $connect = 5.0, public float $read = 30.0, public float $total = 35.0)
    {
        foreach ([$connect, $read, $total] as $timeout) {
            if (! is_finite($timeout) || $timeout <= 0) {
                throw new InvalidArgumentException('Timeouts must be finite positive seconds.');
            }
        }
        if ($total < $connect) {
            throw new InvalidArgumentException('Total timeout must be at least the connect timeout.');
        }
    }
}
