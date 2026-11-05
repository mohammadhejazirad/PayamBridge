<?php

declare(strict_types=1);

namespace PayamBridge\Data;

use InvalidArgumentException;

final readonly class ChatRef
{
    public function __construct(public ConnectionId $connection, public string $id)
    {
        if ($id === '' || ! mb_check_encoding($id, 'UTF-8')) {
            throw new InvalidArgumentException('Chat ID must be a nonempty UTF-8 string.');
        }
    }
}
