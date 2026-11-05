<?php

declare(strict_types=1);

namespace PayamBridge\Data;

final readonly class IncomingEvent
{
    /** @param array<string, mixed> $raw Unknown fields and unknown event kinds remain available verbatim. */
    public function __construct(
        public ConnectionId $connection,
        public ?string $id,
        public string $kind,
        public ?ChatRef $chat,
        public ?MessageRef $message,
        public ?string $text,
        public array $raw,
    ) {}
}
