<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

use PayamBridge\Data\ChatRef;
use PayamBridge\Data\MessageReceipt;

interface SendsText extends Connection
{
    public function sendText(ChatRef $chat, string $text): MessageReceipt;
}
