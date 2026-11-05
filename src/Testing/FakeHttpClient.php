<?php

declare(strict_types=1);

namespace PayamBridge\Testing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/** Instance-scoped scripted PSR-18 fake. It never contacts the network or invents a success. */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    private array $requests = [];

    /** @param list<ResponseInterface|Throwable> $responses */
    public function __construct(private array $responses = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('FakeHttpClient has no queued response.');
        }
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    /** @return list<RequestInterface> Contains test credentials; do not log in production. */
    public function requests(): array
    {
        return $this->requests;
    }

    public function reset(): void
    {
        $this->requests = [];
        $this->responses = [];
    }
}
