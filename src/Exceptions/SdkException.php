<?php

declare(strict_types=1);

namespace PayamBridge\Exceptions;

use PayamBridge\Http\OperationContext;
use RuntimeException;

class SdkException extends RuntimeException
{
    public function __construct(
        public readonly ErrorKind $kind,
        public readonly OperationContext $context,
        public readonly int|string|null $providerCode = null,
        public readonly ?int $retryAfter = null,
    ) {
        // Do not attach upstream exceptions: their messages/requests can contain credentials.
        parent::__construct(sprintf('%s during %s on %s (correlation %s).', $kind->value, $context->operation, $context->connection->key(), $context->correlationId));
    }
}
