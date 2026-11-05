<?php

declare(strict_types=1);

namespace PayamBridge\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use JsonException;
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;
use stdClass;
use Throwable;

final readonly class HttpTransport
{
    public function __construct(private ClientInterface $client, private LoggerInterface $logger = new NullLogger, private int $maxResponseBytes = 4_194_304)
    {
        if ($maxResponseBytes < 1) {
            throw new \InvalidArgumentException('Response limit must be positive.');
        }
    }

    public static function createDefault(Timeouts $timeouts = new Timeouts, ?LoggerInterface $logger = null): self
    {
        return new self(new Client([
            'connect_timeout' => $timeouts->connect,
            'read_timeout' => $timeouts->read,
            'timeout' => $timeouts->total,
            'verify' => true,
            'allow_redirects' => false,
            'http_errors' => false,
        ]), $logger ?? new NullLogger);
    }

    public function send(#[SensitiveParameter] RequestInterface $request, OperationContext $context): JsonResponse
    {
        try {
            return $this->perform($request, $context);
        } catch (SdkException $exception) {
            try {
                $this->logger->warning('PayamBridge operation failed.', [
                    'connection' => $context->connection->key(),
                    'operation' => $context->operation,
                    'correlation_id' => $context->correlationId,
                    'kind' => $exception->kind->value,
                ]);
            } catch (Throwable) {
                // A failed logging backend must not change the delivery outcome.
            }
            throw $exception;
        }
    }

    private function perform(#[SensitiveParameter] RequestInterface $request, OperationContext $context): JsonResponse
    {
        try {
            $response = $this->client->sendRequest($request);
        } catch (RequestExceptionInterface) {
            throw new SdkException(ErrorKind::Validation, $context);
        } catch (Throwable $exception) {
            if ($context->writes) {
                throw new AmbiguousDeliveryException($context);
            }
            $timeout = $exception instanceof ConnectException && ($exception->getHandlerContext()['errno'] ?? null) === 28;
            throw new SdkException($timeout ? ErrorKind::Timeout : ErrorKind::Transport, $context);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $response->getBody()->close();
            if ($context->writes && ($status >= 500 || ($status >= 300 && $status < 400))) {
                throw new AmbiguousDeliveryException($context);
            }
            $kind = match ($status) {
                401 => ErrorKind::Authorization,
                403 => ErrorKind::Permission,
                400, 422 => ErrorKind::Validation,
                429 => ErrorKind::RateLimit,
                default => ErrorKind::Provider,
            };
            $retry = $response->getHeaderLine('Retry-After');
            $seconds = preg_match('/\A[0-9]{1,9}\z/D', $retry) ? (int) $retry : null;
            throw new SdkException($kind, $context, $status, $seconds);
        }

        try {
            $body = '';
            $stream = $response->getBody();
            while (! $stream->eof()) {
                $chunk = $stream->read(min(8192, $this->maxResponseBytes + 1 - strlen($body)));
                if ($chunk === '') {
                    throw new JsonException('Incomplete response body.');
                }
                $body .= $chunk;
                if (strlen($body) > $this->maxResponseBytes) {
                    throw new \LengthException;
                }
            }
            $object = json_decode($body, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (! $object instanceof stdClass) {
                throw new JsonException('Expected a JSON object.');
            }
            $decoded = [];
            foreach (get_object_vars($object) as $key => $value) {
                $decoded[$key] = $this->preserveJsonTypes($value);
            }
        } catch (Throwable) {
            throw $context->writes ? new AmbiguousDeliveryException($context) : new SdkException(ErrorKind::Protocol, $context);
        } finally {
            $response->getBody()->close();
        }

        return new JsonResponse($decoded, $status);
    }

    private function preserveJsonTypes(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $fields = get_object_vars($value);
            // Associative decoding alone collapses {} and [] and can accept an
            // invalid empty-object update list as an acknowledged empty batch.
            if ($fields === [] || array_is_list($fields)) {
                return $value;
            }

            return array_map($this->preserveJsonTypes(...), $fields);
        }

        return is_array($value) ? array_map($this->preserveJsonTypes(...), $value) : $value;
    }
}
