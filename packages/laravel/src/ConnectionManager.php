<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use PayamBridge\ConnectionFactory;
use PayamBridge\Contracts\Connection;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;

final readonly class ConnectionManager
{
    public function __construct(private Repository $config, private ConnectionFactory $factory, private CredentialResolver $credentials) {}

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->config->get('payambridge.default');
        $definitions = $this->config->get('payambridge.connections', []);
        if (! is_string($name) || ! is_array($definitions) || ! is_array($definitions[$name] ?? null)) {
            throw new InvalidArgumentException('Unknown PayamBridge connection.');
        }
        $definition = $definitions[$name];
        foreach (['provider', 'surface', 'tenant', 'token_env'] as $field) {
            if (! is_string($definition[$field] ?? null)) {
                throw new InvalidArgumentException('Invalid PayamBridge connection definition.');
            }
        }
        $provider = Provider::tryFrom($definition['provider']);
        $surface = Surface::tryFrom($definition['surface']);
        if ($provider === null || $surface === null) {
            throw new InvalidArgumentException('Unknown PayamBridge provider or identity surface.');
        }
        $id = new ConnectionId($provider, $surface, $name, $definition['tenant']);

        // Each call creates a new immutable, scoped driver and resolves the current
        // secret. No mutable "current tenant" or credential cache survives a job.
        return $this->factory->create($id, $this->credentials->resolve($definition['token_env']));
    }
}
