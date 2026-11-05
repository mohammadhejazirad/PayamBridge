<?php

declare(strict_types=1);

namespace PayamBridge\Exceptions;

enum ErrorKind: string
{
    case Transport = 'transport';
    case Timeout = 'timeout';
    case Provider = 'provider';
    case Authorization = 'authorization';
    case Permission = 'permission';
    case Validation = 'validation';
    case RateLimit = 'rate_limit';
    case Protocol = 'protocol_mismatch';
    case AmbiguousDelivery = 'delivery_unknown';
}
