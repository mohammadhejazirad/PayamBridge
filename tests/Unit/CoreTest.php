<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use PayamBridge\Contracts\Capability;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\CapabilitySet;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Exceptions\UnsupportedCapabilityException;
use PayamBridge\Http\Timeouts;
use PayamBridge\Security\Credential;
use PayamBridge\Support\Wire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoreTest extends TestCase
{
    public function test_scope_includes_provider_identity_tenant_and_connection(): void
    {
        $id = new ConnectionId(Provider::Bale, Surface::Bot, 'orders', 'shop-a');
        self::assertSame('bale:bot:shop-a:orders', $id->key());
        self::assertFalse($id->equals(new ConnectionId(Provider::Bale, Surface::Bot, 'orders', 'shop-b')));
        self::assertFalse($id->equals(new ConnectionId(Provider::Rubika, Surface::Bot, 'orders', 'shop-a')));
    }

    public function test_capabilities_are_explicit_and_unique(): void
    {
        $set = new CapabilitySet(Capability::SendsText, Capability::SendsText);
        self::assertSame(['sends_text'], $set->values());
        self::assertFalse($set->supports(Capability::ReceivesUpdates));
        $this->expectException(UnsupportedCapabilityException::class);
        $set->require(Capability::ReceivesUpdates);
    }

    public function test_credential_is_redacted_and_cannot_enter_a_queue_payload(): void
    {
        $credential = new Credential('secret-token');
        ob_start();
        var_dump($credential);
        $dump = ob_get_clean();
        self::assertStringNotContainsString('secret-token', $dump);
        self::assertStringContainsString('[REDACTED]', $dump);
        self::assertStringNotContainsString('secret-token', var_export($credential, true));
        $this->expectException(LogicException::class);
        serialize($credential);
    }

    public static function invalidCredentials(): iterable
    {
        foreach (['', 'has whitespace', 'a/b', 'a?b', 'a#b', "a\r\nb", 'https://example.test'] as $value) {
            yield [$value];
        }
    }

    #[DataProvider('invalidCredentials')]
    public function test_credential_cannot_change_the_api_origin_or_path(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Credential($value);
    }

    public static function invalidIntegers(): iterable
    {
        foreach (['', '01', '+1', '1.0', '1e4', ' 1', '9223372036854775808', '-9223372036854775809'] as $value) {
            yield [$value];
        }
    }

    #[DataProvider('invalidIntegers')]
    public function test_wire_integer_never_silently_truncates_ids(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Wire::integer($value);
    }

    public function test_integer_zero_and_negative_ids_are_preserved(): void
    {
        self::assertSame(0, Wire::integer('0'));
        self::assertSame(-10012, Wire::integer('-10012'));
    }

    public function test_nonfinite_timeouts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Timeouts(total: INF);
    }
}
