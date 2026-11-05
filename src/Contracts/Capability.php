<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

enum Capability: string
{
    case SendsText = 'sends_text';
    case ReceivesUpdates = 'receives_updates';
    case GetsIdentity = 'gets_identity';
    case DurablePolling = 'durable_polling';
}
