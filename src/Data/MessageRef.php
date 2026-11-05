<?php

declare(strict_types=1);

namespace PayamBridge\Data;

use InvalidArgumentException;

final readonly class MessageRef
{
    public function __construct(public ChatRef $chat, public string $id)
    {
        if ($id === '') {
            throw new InvalidArgumentException('Message ID must not be empty.');
        }
    }
}
