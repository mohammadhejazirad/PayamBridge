<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Data\IncomingEvent;
use PayamBridge\Data\UpdateBatch;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Drivers\Rubika\RubikaBot;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Exceptions\UnsupportedCapabilityException;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Runtime\LeaseLostException;
use PayamBridge\Runtime\LeaseUnavailableException;
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;
use PayamBridge\Security\Credential;
use PayamBridge\Testing\FakeHttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteInboxTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'payambridge-');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    private function connection(string $tenant = 'a'): ConnectionId
    {
        return new ConnectionId(Provider::Bale, Surface::Bot, 'orders', $tenant);
    }

    private function store(?\Closure $clock = null): SqliteInboxStore
    {
        $store = new SqliteInboxStore(new PDO('sqlite:'.$this->path), $clock);
        $store->initialize();

        return $store;
    }

    private function event(string $id, ?ConnectionId $connection = null): IncomingEvent
    {
        return new IncomingEvent($connection ?? $this->connection(), $id, 'message', null, null, 'سلام', ['update_id' => $id]);
    }

    public function test_cursor_and_events_survive_restart_without_duplicate_inserts(): void
    {
        $first = $this->store();
        $lease = $first->acquire($this->connection(), 60);
        self::assertSame(1, $first->checkpoint($lease, new UpdateBatch([$this->event('1')], '2')));
        $first->release($lease);
        unset($first);

        $restarted = $this->store();
        $lease = $restarted->acquire($this->connection(), 60);
        self::assertSame('2', $restarted->cursor($lease));
        self::assertSame(0, $restarted->checkpoint($lease, new UpdateBatch([$this->event('1')], '2')));
        self::assertCount(1, $restarted->events($this->connection()));
        self::assertCount(0, $restarted->events($this->connection('b')));
    }

    public function test_two_independent_connections_cannot_hold_the_same_live_lease(): void
    {
        $first = $this->store();
        $second = $this->store();
        $first->acquire($this->connection(), 60);
        $this->expectException(LeaseUnavailableException::class);
        $second->acquire($this->connection(), 60);
    }

    public function test_expired_worker_is_fenced_even_if_it_releases_after_takeover(): void
    {
        $now = 100.0;
        $clock = static function () use (&$now): float {
            return $now;
        };
        $first = $this->store($clock);
        $second = $this->store($clock);
        $stale = $first->acquire($this->connection(), 10);
        $now = 111.0;
        $current = $second->acquire($this->connection(), 10);
        self::assertGreaterThan($stale->fence, $current->fence);
        $first->release($stale);
        self::assertSame(1, $second->checkpoint($current, new UpdateBatch([$this->event('2')], '3')));
        try {
            $first->checkpoint($stale, new UpdateBatch([$this->event('1')], '2'));
            self::fail();
        } catch (LeaseLostException) {
            self::assertSame('3', $second->cursor($current));
            self::assertCount(1, $second->events($this->connection()));
        }
    }

    public function test_batch_error_rolls_back_all_insertions_and_cursor(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
        try {
            $store->checkpoint($lease, new UpdateBatch([$this->event('2'), $this->event('3', $this->connection('b'))], '4'));
            self::fail();
        } catch (InvalidArgumentException) {
            self::assertSame('2', $store->cursor($lease));
            self::assertSame(['1'], array_column($store->events($this->connection()), 'event_id'));
        }
    }

    public function test_database_failure_before_cursor_update_rolls_back_events(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $pdo = new PDO('sqlite:'.$this->path);
        $pdo->exec("CREATE TRIGGER fail_cursor BEFORE INSERT ON payambridge_cursors BEGIN SELECT RAISE(ABORT, 'simulated disk error'); END");
        try {
            $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
            self::fail();
        } catch (\PDOException) {
            self::assertSame([], $store->events($this->connection()));
            self::assertNull($store->cursor($lease));
        }
        $pdo->exec('DROP TRIGGER fail_cursor');
        self::assertSame(1, $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2')));
    }

    public function test_poller_restarts_from_persisted_cursor_and_deduplicates_replay(): void
    {
        $body = '{"ok":true,"result":[{"update_id":1,"future_event":{}}]}';
        $fake = new FakeHttpClient([new Response(200, [], $body), new Response(200, [], $body)]);
        $driver = new BaleBot($this->connection(), new Credential('test-token'), new HttpTransport($fake));
        self::assertSame(1, (new Poller($this->store()))->once($driver));
        self::assertSame(0, (new Poller($this->store()))->once($driver));
        self::assertSame(['limit' => 100, 'offset' => 2], json_decode((string) $fake->requests()[1]->getBody(), true));
    }

    public function test_rubika_durable_polling_is_blocked_before_http_without_a_stable_event_identity(): void
    {
        $id = new ConnectionId(Provider::Rubika, Surface::Bot, 'test');
        $fake = new FakeHttpClient;
        $driver = new RubikaBot($id, new Credential('test-token'), new HttpTransport($fake));
        try {
            (new Poller($this->store()))->once($driver);
            self::fail();
        } catch (UnsupportedCapabilityException) {
            self::assertSame([], $fake->requests());
        }
    }

    public function test_expiration_during_batch_persistence_rolls_back_everything(): void
    {
        $calls = 0;
        $clock = static function () use (&$calls): float {
            // acquire, first lease check, event timestamp, final lease check
            return ++$calls >= 4 ? 200.0 : 100.0;
        };
        $store = $this->store($clock);
        $lease = $store->acquire($this->connection(), 10);
        try {
            $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
            self::fail();
        } catch (LeaseLostException) {
            self::assertSame([], $store->events($this->connection()));
        }
        $next = $store->acquire($this->connection(), 10);
        self::assertNull($store->cursor($next));
    }

    public function test_fetch_failure_releases_the_lease_without_advancing_cursor(): void
    {
        $store = $this->store();
        $driver = new BaleBot($this->connection(), new Credential('test-token'), new HttpTransport(new FakeHttpClient([new \RuntimeException('disconnected')])));
        try {
            (new Poller($store))->once($driver);
            self::fail();
        } catch (SdkException) {
            $lease = $store->acquire($this->connection(), 10);
            self::assertNull($store->cursor($lease));
            self::assertSame([], $store->events($this->connection()));
        }
    }
}
