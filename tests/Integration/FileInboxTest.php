<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Integration;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use JsonException;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Data\IncomingEvent;
use PayamBridge\Data\UpdateBatch;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\LeaseLostException;
use PayamBridge\Runtime\LeaseUnavailableException;
use PayamBridge\Runtime\Poller;
use PayamBridge\Security\Credential;
use PayamBridge\Testing\FakeHttpClient;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Throwable;

final class FileInboxTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/payambridge-file-inbox-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
        $this->path = $this->directory.'/inbox.txt';
    }

    protected function tearDown(): void
    {
        foreach (new \DirectoryIterator($this->directory) as $entry) {
            if (! $entry->isDot()) {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->directory);
    }

    private function connection(string $tenant = 'a', string $name = 'orders'): ConnectionId
    {
        return new ConnectionId(Provider::Bale, Surface::Bot, $name, $tenant);
    }

    private function event(string $id, ?ConnectionId $connection = null): IncomingEvent
    {
        return new IncomingEvent($connection ?? $this->connection(), $id, 'message', null, null, 'سلام', ['update_id' => $id, 'text' => 'سلام']);
    }

    private function store(?\Closure $clock = null): FileInboxStore
    {
        $store = new FileInboxStore($this->path, $clock);
        $store->initialize();

        return $store;
    }

    public function test_persisted_cursor_and_utf8_events_survive_restart_and_replay(): void
    {
        $first = $this->store();
        $lease = $first->acquire($this->connection(), 60);
        self::assertSame(1, $first->checkpoint($lease, new UpdateBatch([$this->event('1')], '2')));
        $first->release($lease);
        unset($first);

        $text = file_get_contents($this->path);
        self::assertIsString($text);
        self::assertJson($text);
        self::assertStringContainsString('سلام', $text);
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0600, fileperms($this->path) & 0777);
            self::assertSame(0600, fileperms($this->path.'.lock') & 0777);
        }

        $restarted = $this->store();
        $next = $restarted->acquire($this->connection(), 60);
        self::assertGreaterThan($lease->fence, $next->fence);
        self::assertSame('2', $restarted->cursor($next));
        self::assertSame(0, $restarted->checkpoint($next, new UpdateBatch([$this->event('1')], '2')));
        $events = $restarted->events($this->connection());
        self::assertSame(['1'], array_column($events, 'event_id'));
        self::assertSame(['message'], array_column($events, 'kind'));
        self::assertSame($this->event('1')->raw, json_decode($events[0]['payload'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_null_next_cursor_preserves_checkpoint_and_scopes_are_independent(): void
    {
        $store = $this->store();
        $first = $store->acquire($this->connection(), 60);
        $other = $store->acquire($this->connection('b'), 60);
        $store->checkpoint($first, new UpdateBatch([$this->event('1')], '2'));
        self::assertSame(1, $store->checkpoint($first, new UpdateBatch([$this->event('2')], null)));
        self::assertSame(1, $store->checkpoint($other, new UpdateBatch([$this->event('1', $this->connection('b'))], '99')));

        self::assertSame('2', $store->cursor($first));
        self::assertSame('99', $store->cursor($other));
        self::assertSame(['1', '2'], array_column($store->events($this->connection()), 'event_id'));
        self::assertSame(['1'], array_column($store->events($this->connection('b')), 'event_id'));
        self::assertSame([], $store->events($this->connection('a', 'billing')));
    }

    public function test_processes_contend_for_lease_and_fence_survives_release(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $held = $this->childAcquire();
        self::assertSame(0, $held['exit']);
        self::assertSame(LeaseUnavailableException::class, $held['result']['error']);

        $store->release($lease);
        $acquired = $this->childAcquire();
        self::assertSame(0, $acquired['exit']);
        self::assertSame($lease->fence + 1, $acquired['result']['fence']);

        $next = $store->acquire($this->connection(), 60);
        self::assertGreaterThan($acquired['result']['fence'], $next->fence);
        $this->expectException(LeaseLostException::class);
        $store->cursor($lease);
    }

    public function test_adjacent_lock_serializes_a_separate_process(): void
    {
        $this->store();
        $lock = fopen($this->path.'.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX));

        $fixture = dirname(__DIR__).'/Fixtures/file-inbox-acquire.php';
        $process = proc_open([PHP_BINARY, $fixture, $this->path, 'signal'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            self::assertSame("ready\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            self::assertSame(0, stream_select($read, $write, $except, 0, 200000));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        self::assertSame('', $error);
        self::assertSame(1, json_decode($output, true, 512, JSON_THROW_ON_ERROR)['fence']);
    }

    public function test_expired_worker_cannot_checkpoint_or_release_new_owner(): void
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
        $first->release($stale);
        self::assertSame(1, $second->checkpoint($current, new UpdateBatch([$this->event('2')], '3')));
        try {
            $first->checkpoint($stale, new UpdateBatch([$this->event('1')], '2'));
            self::fail('Stale worker checkpoint succeeded.');
        } catch (LeaseLostException) {
            self::assertSame('3', $second->cursor($current));
            self::assertSame(['2'], array_column($second->events($this->connection()), 'event_id'));
        }
    }

    public function test_expiration_during_checkpoint_rolls_back_events_and_cursor(): void
    {
        $calls = 0;
        $clock = static function () use (&$calls): float {
            return ++$calls >= 4 ? 200.0 : 100.0;
        };
        $store = $this->store($clock);
        $lease = $store->acquire($this->connection(), 10);
        $before = file_get_contents($this->path);
        try {
            $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
            self::fail('Expired checkpoint succeeded.');
        } catch (LeaseLostException) {
            self::assertSame($before, file_get_contents($this->path));
            self::assertSame([], $store->events($this->connection()));
        }
        $next = $store->acquire($this->connection(), 10);
        self::assertNull($store->cursor($next));
    }

    public function test_invalid_event_and_missing_id_do_not_partially_write_batch(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
        foreach ([$this->event('wrong', $this->connection('b')), new IncomingEvent($this->connection(), null, 'message', null, null, null, [])] as $invalid) {
            $before = file_get_contents($this->path);
            try {
                $store->checkpoint($lease, new UpdateBatch([$this->event('2'), $invalid], '4'));
                self::fail('Invalid batch succeeded.');
            } catch (InvalidArgumentException) {
                self::assertSame($before, file_get_contents($this->path));
                self::assertSame('2', $store->cursor($lease));
                self::assertSame(['1'], array_column($store->events($this->connection()), 'event_id'));
            }
        }
    }

    public function test_json_encoding_error_does_not_partially_write_batch(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $bad = new IncomingEvent($this->connection(), 'bad', 'message', null, null, null, ['number' => NAN]);
        $before = file_get_contents($this->path);
        try {
            $store->checkpoint($lease, new UpdateBatch([$this->event('1'), $bad], '2'));
            self::fail('Invalid JSON batch succeeded.');
        } catch (JsonException) {
            self::assertSame($before, file_get_contents($this->path));
            self::assertSame([], $store->events($this->connection()));
            self::assertNull($store->cursor($lease));
        }
    }

    public function test_corrupt_file_fails_closed_without_replacing_it(): void
    {
        $store = $this->store();
        foreach (['', '{"truncated":', '{}', '{"version":2,"connections":{}}', '{"version":1,"connections":{"bale:bot:a:orders":{}}}'] as $corrupt) {
            file_put_contents($this->path, $corrupt);
            foreach ([static fn () => $store->initialize(), fn () => $store->acquire($this->connection(), 60), fn () => $store->events($this->connection())] as $operation) {
                try {
                    $operation();
                    self::fail('Corrupt state was accepted.');
                } catch (Throwable $exception) {
                    self::assertNotInstanceOf(AssertionFailedError::class, $exception);
                    self::assertSame($corrupt, file_get_contents($this->path));
                }
            }
        }
    }

    public function test_orphaned_temporary_file_does_not_change_committed_state(): void
    {
        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
        file_put_contents($this->directory.'/.payambridge-orphan', '{"truncated":');

        $restarted = $this->store();
        self::assertSame('2', $restarted->cursor($lease));
        self::assertSame(['1'], array_column($restarted->events($this->connection()), 'event_id'));
        self::assertSame(1, $restarted->checkpoint($lease, new UpdateBatch([$this->event('2')], '3')));
    }

    public function test_write_failure_preserves_the_prior_committed_snapshot(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Directory permissions are not portable on Windows.');
        }

        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $store->checkpoint($lease, new UpdateBatch([$this->event('1')], '2'));
        $before = file_get_contents($this->path);
        self::assertTrue(chmod($this->directory, 0500));
        try {
            clearstatcache(true, $this->directory);
            if (is_writable($this->directory)) {
                self::markTestSkipped('Current user can still write to the restricted directory.');
            }
            try {
                $store->checkpoint($lease, new UpdateBatch([$this->event('2')], '3'));
                self::fail('Checkpoint succeeded in a read-only directory.');
            } catch (\RuntimeException $exception) {
                self::assertNotInstanceOf(AssertionFailedError::class, $exception);
            }
        } finally {
            chmod($this->directory, 0700);
        }

        self::assertSame($before, file_get_contents($this->path));
        self::assertSame('2', $store->cursor($lease));
        self::assertSame(['1'], array_column($store->events($this->connection()), 'event_id'));
    }

    public function test_explicit_initialization_and_existing_parent_are_required(): void
    {
        $store = new FileInboxStore($this->path);
        try {
            $store->acquire($this->connection(), 60);
            self::fail('Store operated before initialization.');
        } catch (Throwable $exception) {
            self::assertNotInstanceOf(AssertionFailedError::class, $exception);
            self::assertFileDoesNotExist($this->path);
        }

        $missingParent = $this->directory.'/missing/inbox.txt';
        try {
            (new FileInboxStore($missingParent))->initialize();
            self::fail('Store created a missing parent directory.');
        } catch (Throwable $exception) {
            self::assertNotInstanceOf(AssertionFailedError::class, $exception);
            self::assertDirectoryDoesNotExist(dirname($missingParent));
        }
    }

    public function test_events_limit_and_poller_restart_work_without_sqlite(): void
    {
        $body = '{"ok":true,"result":[{"update_id":1,"future_event":{}}]}';
        $fake = new FakeHttpClient([new Response(200, [], $body), new Response(200, [], $body)]);
        $driver = new BaleBot($this->connection(), new Credential('test-token'), new HttpTransport($fake));
        self::assertSame(1, (new Poller($this->store()))->once($driver));
        self::assertSame(0, (new Poller($this->store()))->once($driver));
        self::assertSame(['limit' => 100, 'offset' => 2], json_decode((string) $fake->requests()[1]->getBody(), true));

        $store = $this->store();
        $lease = $store->acquire($this->connection(), 60);
        $store->checkpoint($lease, new UpdateBatch([$this->event('2'), $this->event('3')], '4'));
        self::assertCount(1, $store->events($this->connection(), 1));
        self::assertCount(3, $store->events($this->connection(), 1000));
        foreach ([0, 1001] as $limit) {
            try {
                $store->events($this->connection(), $limit);
                self::fail('Invalid limit accepted.');
            } catch (InvalidArgumentException) {
                // The same read bounds apply to both durable stores.
            }
        }
    }

    /** @return array{exit: int, result: array<string, mixed>} */
    private function childAcquire(): array
    {
        $fixture = dirname(__DIR__).'/Fixtures/file-inbox-acquire.php';
        $process = proc_open([PHP_BINARY, $fixture, $this->path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame('', $error);
        self::assertIsString($output);

        return ['exit' => $exit, 'result' => json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
    }
}
