<?php

declare(strict_types=1);

namespace PayamBridge\Runtime;

use PayamBridge\Contracts\Capability;
use PayamBridge\Contracts\ReceivesUpdates;

final readonly class Poller
{
    public function __construct(private InboxStore $inbox, private float $leaseSeconds = 60.0) {}

    /** Fetches one periodic batch. No message handler runs before durable persistence. */
    public function once(ReceivesUpdates $connection): int
    {
        $connection->capabilities()->require(Capability::DurablePolling);
        $lease = $this->inbox->acquire($connection->id(), $this->leaseSeconds);
        try {
            $batch = $connection->fetchUpdates($this->inbox->cursor($lease));

            return $this->inbox->checkpoint($lease, $batch);
        } finally {
            $this->inbox->release($lease);
        }
    }
}
