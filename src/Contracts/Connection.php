<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

use PayamBridge\Data\CapabilitySet;
use PayamBridge\Data\ConnectionId;

interface Connection
{
    public function id(): ConnectionId;

    public function capabilities(): CapabilitySet;
}
