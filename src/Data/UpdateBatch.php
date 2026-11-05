<?php

declare(strict_types=1);

namespace PayamBridge\Data;

final readonly class UpdateBatch
{
    /** @param list<IncomingEvent> $events */
    public function __construct(public array $events, public ?string $nextCursor) {}
}
