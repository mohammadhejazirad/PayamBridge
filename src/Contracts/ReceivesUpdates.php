<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

use PayamBridge\Data\UpdateBatch;

interface ReceivesUpdates extends Connection
{
    /** Periodic polling; provider-specific long polling is exposed on the native client. */
    public function fetchUpdates(?string $cursor = null): UpdateBatch;
}
