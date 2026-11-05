<?php

declare(strict_types=1);

namespace PayamBridge\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PayamBridge\Contracts\Connection;
use PayamBridge\Laravel\ConnectionManager;

/** @method static Connection connection(?string $name = null) */
final class PayamBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConnectionManager::class;
    }
}
