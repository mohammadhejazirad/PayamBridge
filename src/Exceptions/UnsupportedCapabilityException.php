<?php

declare(strict_types=1);

namespace PayamBridge\Exceptions;

use LogicException;
use PayamBridge\Contracts\Capability;

final class UnsupportedCapabilityException extends LogicException
{
    public function __construct(public readonly Capability $capability)
    {
        parent::__construct('Unsupported capability: '.$capability->value);
    }
}
