<?php

declare(strict_types=1);

namespace PayamBridge\Drivers;

use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use PayamBridge\Contracts\Connection;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\OperationContext;
use PayamBridge\Security\Credential;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;
use Throwable;

abstract class NativeClient implements Connection
{
    protected const ?Provider PROVIDER = null;

    protected const ?Surface SURFACE = null;

    private RequestFactoryInterface $requests;

    private StreamFactoryInterface $streams;

    public function __construct(
        protected readonly ConnectionId $connection,
        protected readonly Credential $credential,
        protected readonly HttpTransport $transport,
        ?RequestFactoryInterface $requests = null,
        ?StreamFactoryInterface $streams = null,
    ) {
        $this->requests = $requests ?? new HttpFactory;
        $this->streams = $streams ?? new HttpFactory;
        if ($connection->provider !== static::PROVIDER || $connection->surface !== static::SURFACE) {
            throw new InvalidArgumentException('Connection provider/surface does not match the driver.');
        }
    }

    final public function id(): ConnectionId
    {
        return $this->connection;
    }

    final protected function assertScope(ChatRef $chat): void
    {
        if (! $chat->connection->equals($this->connection)) {
            throw new InvalidArgumentException('Chat belongs to a different provider, tenant or connection.');
        }
    }

    abstract protected function endpoint(string $method): string;

    /** @param array<string, mixed> $body */
    abstract protected function unwrap(array $body, OperationContext $context): mixed;

    /**
     * @template T
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(mixed, OperationContext): T  $decode
     * @return T
     */
    final protected function execute(string $method, #[SensitiveParameter] array $payload, bool $writes, callable $decode): mixed
    {
        $context = new OperationContext($this->connection, $method, $writes);
        try {
            $request = $this->requests->createRequest('POST', $this->endpoint($method))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json')
                ->withBody($this->streams->createStream(json_encode((object) $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
        } catch (Throwable) {
            // Request construction is local: nothing has reached the provider.
            // Factory exceptions may include the credential-bearing URI or body.
            throw new SdkException(ErrorKind::Validation, $context);
        }
        $response = $this->transport->send($request, $context);

        try {
            return $decode($this->unwrap($response->body, $context), $context);
        } catch (SdkException $exception) {
            if ($writes && $exception->kind === ErrorKind::Protocol) {
                throw new AmbiguousDeliveryException($context);
            }
            throw $exception;
        }
    }
}
