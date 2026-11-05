<?php

declare(strict_types=1);

namespace PayamBridge;

use InvalidArgumentException;
use PayamBridge\Contracts\Connection;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Drivers\Eitaa\EitaaApp;
use PayamBridge\Drivers\Rubika\RubikaBot;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;

final readonly class ConnectionFactory
{
    public function __construct(private HttpTransport $transport) {}

    public function create(ConnectionId $id, Credential $credential): Connection
    {
        return match ($id->provider->value.'/'.$id->surface->value) {
            'bale/bot' => new BaleBot($id, $credential, $this->transport),
            'rubika/bot' => new RubikaBot($id, $credential, $this->transport),
            'eitaa/app' => new EitaaApp($id, $credential, $this->transport),
            default => throw new InvalidArgumentException('This provider/identity surface has no implemented driver.'),
        };
    }
}
