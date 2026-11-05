<?php

declare(strict_types=1);

namespace PayamBridge\Laravel\Notifications;

use PayamBridge\Support\Wire;

final readonly class TextMessage
{
    public function __construct(public string $text)
    {
        Wire::text($text);
    }
}
