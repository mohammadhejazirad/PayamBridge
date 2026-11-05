<?php

declare(strict_types=1);

namespace PayamBridge\Data;

final readonly class BotIdentity
{
    /** @param array<string, mixed> $raw */
    public function __construct(public ConnectionId $connection, public string $id, public ?string $name, public ?string $username, public array $raw) {}
}
