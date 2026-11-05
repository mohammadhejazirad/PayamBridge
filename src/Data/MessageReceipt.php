<?php

declare(strict_types=1);

namespace PayamBridge\Data;

final readonly class MessageReceipt
{
    /** @param array<string, mixed> $raw Sensitive provider data; never log without a consumer retention policy. */
    public function __construct(public ChatRef $chat, public ?MessageRef $message, public array $raw) {}
}
