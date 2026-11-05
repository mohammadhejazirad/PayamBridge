<?php

declare(strict_types=1);

namespace PayamBridge\Laravel\Notifications;

use InvalidArgumentException;

final readonly class NotificationRoute
{
    public function __construct(public string $connection, public string $chatId)
    {
        if ($connection === '' || $chatId === '') {
            throw new InvalidArgumentException('A connection name and chat ID are required.');
        }
    }
}
