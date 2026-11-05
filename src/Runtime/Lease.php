<?php

declare(strict_types=1);

namespace PayamBridge\Runtime;

use PayamBridge\Data\ConnectionId;

final readonly class Lease
{
    public function __construct(public ConnectionId $connection, public string $owner, public int $fence) {}
}
