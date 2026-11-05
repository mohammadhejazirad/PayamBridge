<?php

declare(strict_types=1);

namespace PayamBridge\Runtime;

use PayamBridge\Data\ConnectionId;
use PayamBridge\Data\UpdateBatch;

interface InboxStore
{
    public function acquire(ConnectionId $connection, float $ttl): Lease;

    public function cursor(Lease $lease): ?string;

    /** Atomically persists every event and the next cursor, fenced by the current lease. */
    public function checkpoint(Lease $lease, UpdateBatch $batch): int;

    public function release(Lease $lease): void;
}
