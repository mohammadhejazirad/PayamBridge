<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

use PayamBridge\Data\BotIdentity;

interface GetsIdentity extends Connection
{
    public function getMe(): BotIdentity;
}
