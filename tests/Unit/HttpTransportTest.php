<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\OperationContext;
use PayamBridge\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\AbstractLogger;

final class HttpTransportTest extends TestCase
{
    private function context(bool $writes = false): OperationContext
    {
        return new OperationContext(new ConnectionId(Provider::Bale, Surface::Bot, 'test'), 'sendMessage', $writes);
    }

    public static function errors(): iterable
    {
        yield [401, ErrorKind::Authorization];
        yield [403, ErrorKind::Permission];
        yield [400, ErrorKind::Validation];
        yield [422, ErrorKind::Validation];
        yield [429, ErrorKind::RateLimit];
        yield [503, ErrorKind::Provider];
    }

    #[DataProvider('errors')]
    public function test_non_json_http_errors_are_classified(int $status, ErrorKind $kind): void
    {
        $fake = new FakeHttpClient([new Response($status, ['Retry-After' => '0'], 'upstream HTML with secret-token')]);
        try {
            (new HttpTransport($fake))->send(new Request('POST', 'https://example.test/secret-token'), $this->context());
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame($kind, $exception->kind);
            self::assertSame(0, $exception->retryAfter);
            self::assertStringNotContainsString('secret-token', (string) $exception);
        }
    }

    public function test_write_http_5xx_is_ambiguous_and_never_retried(): void
    {
        $fake = new FakeHttpClient([new Response(503, [], '{}')]);
        try {
            (new HttpTransport($fake))->send(new Request('POST', 'https://example.test'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
            self::assertCount(1, $fake->requests());
        }
    }

    public function test_redirect_is_never_followed(): void
    {
        $fake = new FakeHttpClient([new Response(302, ['Location' => 'https://attacker.test'], '')]);
        try {
            (new HttpTransport($fake))->send(new Request('POST', 'https://example.test/secret-token'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
            self::assertCount(1, $fake->requests());
        }
    }

    public function test_large_json_integers_and_empty_objects_keep_their_types(): void
    {
        $fake = new FakeHttpClient([new Response(200, [], '{"id":900719925474099312345,"object":{},"list":[],"null":null,"zero":0,"false":false,"empty":""}')]);
        $body = (new HttpTransport($fake))->send(new Request('POST', 'https://example.test'), $this->context())->body;
        self::assertSame('900719925474099312345', $body['id']);
        self::assertInstanceOf(\stdClass::class, $body['object']);
        self::assertSame([], $body['list']);
        self::assertNull($body['null']);
        self::assertSame(0, $body['zero']);
        self::assertFalse($body['false']);
        self::assertSame('', $body['empty']);
    }

    public function test_response_byte_limit_is_enforced(): void
    {
        $fake = new FakeHttpClient([new Response(200, [], '{"large":"abcdefghijk"}')]);
        $this->expectException(SdkException::class);
        (new HttpTransport($fake, maxResponseBytes: 8))->send(new Request('POST', 'https://example.test'), $this->context());
    }

    public function test_body_access_failure_keeps_write_delivery_ambiguous(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willThrowException(new \RuntimeException('secret-token body error'));
        try {
            (new HttpTransport(new FakeHttpClient([$response])))->send(new Request('POST', 'https://example.test'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
            self::assertStringNotContainsString('secret-token', (string) $exception);
        }
    }

    public function test_status_access_failure_is_sanitized_after_send(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willThrowException(new \RuntimeException('secret-token status error'));
        try {
            (new HttpTransport(new FakeHttpClient([$response])))->send(new Request('POST', 'https://example.test'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
            self::assertStringNotContainsString('secret-token', (string) $exception);
        }
    }

    public function test_cleanup_failure_does_not_mask_http_error_or_valid_response(): void
    {
        $broken = $this->createStub(StreamInterface::class);
        $broken->method('close')->willThrowException(new \RuntimeException('secret-token close error'));
        $failure = $this->createStub(ResponseInterface::class);
        $failure->method('getStatusCode')->willReturn(503);
        $failure->method('getBody')->willReturn($broken);
        try {
            (new HttpTransport(new FakeHttpClient([$failure])))->send(new Request('POST', 'https://example.test'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
        }

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->willReturn('{"ok":true}');
        $stream->method('close')->willThrowException(new \RuntimeException('secret-token close error'));
        $success = $this->createStub(ResponseInterface::class);
        $success->method('getStatusCode')->willReturn(200);
        $success->method('getBody')->willReturn($stream);
        self::assertSame(['ok' => true], (new HttpTransport(new FakeHttpClient([$success])))->send(new Request('POST', 'https://example.test'), $this->context(true))->body);
    }

    public function test_log_contains_only_public_context(): void
    {
        $logger = new class extends AbstractLogger
        {
            public array $entries = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->entries[] = [$level, (string) $message, $context];
            }
        };
        $fake = new FakeHttpClient([new \RuntimeException('secret-token message content')]);
        try {
            (new HttpTransport($fake, $logger))->send(new Request('POST', 'https://example.test/secret-token', [], 'private-message'), $this->context(true));
        } catch (SdkException) {
            $log = json_encode($logger->entries);
            self::assertStringNotContainsString('secret-token', $log);
            self::assertStringNotContainsString('private-message', $log);
            self::assertStringContainsString('correlation_id', $log);
        }
    }

    public function test_fake_has_no_global_state_or_implicit_success(): void
    {
        $first = new FakeHttpClient([new Response(200, [], '{}')]);
        $second = new FakeHttpClient;
        $first->sendRequest(new Request('GET', 'https://example.test'));
        self::assertCount(0, $second->requests());
        $first->reset();
        self::assertSame([], $first->requests());
        $this->expectException(\RuntimeException::class);
        $second->sendRequest(new Request('GET', 'https://example.test'));
    }

    public function test_logger_failure_does_not_mask_an_ambiguous_write(): void
    {
        $logger = new class extends AbstractLogger
        {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('log sink unavailable');
            }
        };
        $fake = new FakeHttpClient([new \RuntimeException('connection lost')]);
        try {
            (new HttpTransport($fake, $logger))->send(new Request('POST', 'https://example.test'), $this->context(true));
            self::fail();
        } catch (SdkException $exception) {
            self::assertSame(ErrorKind::AmbiguousDelivery, $exception->kind);
        }
    }
}
