<?php

declare(strict_types=1);

namespace PayamBridge\Exceptions;

use PayamBridge\Http\OperationContext;

final class AmbiguousDeliveryException extends SdkException
{
    public function __construct(OperationContext $context)
    {
        parent::__construct(ErrorKind::AmbiguousDelivery, $context);
    }
}
