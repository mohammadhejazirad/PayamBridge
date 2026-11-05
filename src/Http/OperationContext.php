<?php

declare(strict_types=1);

namespace PayamBridge\Http;

use PayamBridge\Data\ConnectionId;

final readonly class OperationContext
{
    public string $correlationId;

    public function __construct(public ConnectionId $connection, public string $operation, public bool $writes)
    {
        $this->correlationId = bin2hex(random_bytes(12));
    }
}
