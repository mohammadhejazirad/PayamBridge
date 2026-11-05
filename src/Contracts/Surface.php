<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

enum Surface: string
{
    case Bot = 'bot';
    case App = 'app';
}
