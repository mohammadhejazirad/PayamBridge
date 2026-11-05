<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Contract;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PayamBridge\ConnectionFactory;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Drivers\Eitaa\EitaaApp;
use PayamBridge\Drivers\NativeClient;
use PayamBridge\Drivers\Rubika\RubikaBot;
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;
use PayamBridge\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OfficialDriversTest extends TestCase
{
    private function client(string $provider, array $responses): array
    {
        $fake = new FakeHttpClient($responses);
        $id = new ConnectionId(Provider::from($provider), $provider === 'eitaa' ? Surface::App : Surface::Bot, 'test', 'tenant-a');
        $driver = (new ConnectionFactory(new HttpTransport($fake)))->create($id, new Credential('test-token'));

        return [$driver, $fake, new ChatRef($id, $provider === 'rubika' ? 'c0001' : '123')];
    }

    private function fixture(string $name): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__.'/../Fixtures/'.$name.'.json'));
    }

    public function test_bale_send_uses_its_documented_fields_and_preserves_raw(): void
    {
        [$driver, $fake, $chat] = $this->client('bale', [$this->fixture('bale-send')]);
        self::assertInstanceOf(BaleBot::class, $driver);
        $chat = new ChatRef($chat->connection, '@channel');
        $receipt = $driver->sendMessage($chat, 'سلام دنیا 👩‍💻', '0', ['inline_keyboard' => []]);
        self::assertSame('7', $receipt->message->id);
        self::assertSame('-10012', $receipt->chat->id);
        self::assertFalse($receipt->raw['future_flag']);
        $request = $fake->requests()[0];
        self::assertSame('https://tapi.bale.ai/bottest-token/sendMessage', (string) $request->getUri());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['chat_id' => '@channel', 'text' => 'سلام دنیا 👩‍💻', 'reply_to_message_id' => 0, 'reply_markup' => ['inline_keyboard' => []]], json_decode((string) $request->getBody(), true));
    }

    public function test_bale_identity_uses_empty_object_body(): void
    {
        [$driver, $fake] = $this->client('bale', [$this->fixture('bale-me')]);
        $me = $driver->getMe();
        self::assertSame('123', $me->id);
        self::assertSame('پیام‌بریج', $me->name);
        self::assertSame(0, $me->raw['future_field']);
        self::assertSame('{}', (string) $fake->requests()[0]->getBody());
    }

    public function test_bale_cursor_uses_maximum_update_id_and_unknown_events_survive(): void
    {
        [$driver, $fake] = $this->client('bale', [$this->fixture('bale-updates')]);
        $batch = $driver->getUpdates('0', 2, 0);
        self::assertSame('43', $batch->nextCursor);
        self::assertSame('سلام دنیا 👩‍💻', $batch->events[0]->text);
        self::assertSame('unknown', $batch->events[1]->kind);
        self::assertSame(['value' => false], $batch->events[1]->raw['future_event']);
        self::assertSame(['limit' => 2, 'offset' => 0, 'timeout' => 0], json_decode((string) $fake->requests()[0]->getBody(), true));
    }

    public function test_bale_empty_batch_preserves_cursor(): void
    {
        [$driver] = $this->client('bale', [new Response(200, [], '{"ok":true,"result":[]}')]);
        self::assertSame('43', $driver->getUpdates('43')->nextCursor);
    }

    public function test_bale_stale_batch_never_moves_the_cursor_backwards(): void
    {
        [$driver] = $this->client('bale', [$this->fixture('bale-updates')]);
        self::assertSame('50', $driver->getUpdates('50')->nextCursor);
    }

    public function test_rubika_send_preserves_false_and_string_ids(): void
    {
        [$driver, $fake, $chat] = $this->client('rubika', [$this->fixture('rubika-send')]);
        self::assertInstanceOf(RubikaBot::class, $driver);
        $receipt = $driver->sendMessage($chat, 'سلام دنیا 👩‍💻', false, '0', ['rows' => []]);
        self::assertSame('000900719925474099312345', $receipt->message->id);
        self::assertSame('https://botapi.rubika.ir/v3/test-token/sendMessage', (string) $fake->requests()[0]->getUri());
        self::assertSame(['chat_id' => 'c0001', 'text' => 'سلام دنیا 👩‍💻', 'disable_notification' => false, 'reply_to_message_id' => '0', 'inline_keypad' => ['rows' => []]], json_decode((string) $fake->requests()[0]->getBody(), true));
    }

    public function test_rubika_identity_documented_shape(): void
    {
        [$driver] = $this->client('rubika', [$this->fixture('rubika-me')]);
        self::assertSame('b0001', $driver->getMe()->id);
    }

    public function test_rubika_identity_reference_client_shape(): void
    {
        [$driver] = $this->client('rubika', [new Response(200, [], '{"status":"OK","data":{"bot_id":"b0001"}}')]);
        self::assertSame('b0001', $driver->getMe()->id);
    }

    public function test_rubika_cursor_is_opaque_and_no_long_polling_timeout_is_invented(): void
    {
        [$driver, $fake] = $this->client('rubika', [$this->fixture('rubika-updates')]);
        $batch = $driver->getUpdates('0000', 2);
        self::assertSame('000900719925474099312345', $batch->nextCursor);
        self::assertSame('FutureEvent', $batch->events[1]->kind);
        self::assertNull($batch->events[0]->id);
        self::assertSame(['limit' => 2, 'offset_id' => '0000'], json_decode((string) $fake->requests()[0]->getBody(), true));
    }

    public function test_eitaa_app_uses_its_own_endpoint_and_does_not_fabricate_a_message_id(): void
    {
        [$driver, $fake, $chat] = $this->client('eitaa', [$this->fixture('eitaa-app-send')]);
        self::assertInstanceOf(EitaaApp::class, $driver);
        $receipt = $driver->sendText($chat, 'سلام دنیا 👩‍💻');
        self::assertNull($receipt->message);
        self::assertSame('https://eitaayar.ir/api/app/sendMessage', (string) $fake->requests()[0]->getUri());
        self::assertSame(['token' => 'test-token', 'chat_id' => 123, 'text' => 'سلام دنیا 👩‍💻'], json_decode((string) $fake->requests()[0]->getBody(), true));
    }

    public static function operations(): iterable
    {
        foreach (['bale' => ['getMe', 'sendText', 'getUpdates'], 'rubika' => ['getMe', 'sendText', 'getUpdates'], 'eitaa' => ['sendText']] as $provider => $operations) {
            foreach ($operations as $operation) {
                yield $provider.'/'.$operation => [$provider, $operation];
            }
        }
    }

    private function invoke(NativeClient $driver, ChatRef $chat, string $operation): mixed
    {
        return $operation === 'sendText' ? $driver->sendText($chat, 'سلام') : $driver->$operation();
    }

    #[DataProvider('operations')]
    public function test_each_operation_rejects_provider_failure_at_http_200(string $provider, string $operation): void
    {
        $body = $provider === 'rubika' ? '{"status":"INVALID_INPUT"}' : '{"ok":false,"error_code":400,"description":"test-token"}';
        [$driver, $fake, $chat] = $this->client($provider, [new Response(200, [], $body)]);
        try {
            $this->invoke($driver, $chat, $operation);
            self::fail('Expected provider error.');
        } catch (SdkException $exception) {
            self::assertNotSame(ErrorKind::AmbiguousDelivery, $exception->kind);
            self::assertStringNotContainsString('test-token', (string) $exception);
            self::assertNull($exception->getPrevious());
        }
        self::assertCount(1, $fake->requests());
    }

    #[DataProvider('operations')]
    public function test_each_operation_handles_network_timeout_without_retries(string $provider, string $operation): void
    {
        $error = new ConnectException('secret test-token', new Request('POST', 'https://example.test/test-token'), null, ['errno' => 28]);
        [$driver, $fake, $chat] = $this->client($provider, [$error]);
        try {
            $this->invoke($driver, $chat, $operation);
            self::fail('Expected failure.');
        } catch (SdkException $exception) {
            self::assertSame($operation === 'sendText' ? ErrorKind::AmbiguousDelivery : ErrorKind::Timeout, $exception->kind);
            self::assertStringNotContainsString('test-token', (string) $exception);
        }
        self::assertCount(1, $fake->requests());
    }

    #[DataProvider('operations')]
    public function test_each_operation_rejects_non_json(string $provider, string $operation): void
    {
        [$driver, $fake, $chat] = $this->client($provider, [new Response(200, [], '<html>test-token</html>')]);
        try {
            $this->invoke($driver, $chat, $operation);
            self::fail('Expected failure.');
        } catch (SdkException $exception) {
            self::assertSame($operation === 'sendText' ? ErrorKind::AmbiguousDelivery : ErrorKind::Protocol, $exception->kind);
        }
        self::assertCount(1, $fake->requests());
    }

    #[DataProvider('operations')]
    public function test_each_operation_rejects_missing_response_fields(string $provider, string $operation): void
    {
        $body = match ($provider) {
            'bale' => '{"ok":true,"result":{}}',
            'rubika' => '{"status":"OK","data":{}}',
            default => '{"ok":true}',
        };
        [$driver, , $chat] = $this->client($provider, [new Response(200, [], $body)]);
        $this->expectException(SdkException::class);
        $this->invoke($driver, $chat, $operation);
    }

    public function test_invalid_text_fails_before_http(): void
    {
        [$driver, $fake, $chat] = $this->client('bale', []);
        try {
            $driver->sendText($chat, '');
            self::fail();
        } catch (InvalidArgumentException) {
            self::assertSame([], $fake->requests());
        }
    }

    public function test_chat_from_another_tenant_fails_before_http(): void
    {
        [$driver, $fake] = $this->client('bale', []);
        $chat = new ChatRef(new ConnectionId(Provider::Bale, Surface::Bot, 'test', 'tenant-b'), '123');
        try {
            $driver->sendText($chat, 'سلام');
            self::fail();
        } catch (InvalidArgumentException) {
            self::assertSame([], $fake->requests());
        }
    }

    public function test_mismatched_send_receipt_is_ambiguous(): void
    {
        [$driver, , $chat] = $this->client('bale', [$this->fixture('bale-send')]);
        $this->expectException(AmbiguousDeliveryException::class);
        $driver->sendText($chat, 'سلام');
    }

    public static function invalidPayloads(): iterable
    {
        yield 'bale blank text' => ['bale', 'sendText', ['']];
        yield 'rubika blank text' => ['rubika', 'sendText', ['']];
        yield 'eitaa blank text' => ['eitaa', 'sendText', ['']];
        yield 'bale invalid limit' => ['bale', 'getUpdates', [null, 0]];
        yield 'bale overflowing cursor' => ['bale', 'getUpdates', ['9223372036854775808']];
        yield 'rubika invalid limit' => ['rubika', 'getUpdates', [null, 0]];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_request_payloads_fail_before_http(string $provider, string $method, array $arguments): void
    {
        [$driver, $fake, $chat] = $this->client($provider, []);
        if ($method === 'sendText') {
            array_unshift($arguments, $chat);
        }
        try {
            $driver->$method(...$arguments);
            self::fail();
        } catch (InvalidArgumentException) {
            self::assertSame([], $fake->requests());
        }
    }
}
