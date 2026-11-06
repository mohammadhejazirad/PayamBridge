<?php

declare(strict_types=1);

namespace PayamBridge\Runtime;

use Closure;
use InvalidArgumentException;
use JsonException;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Data\UpdateBatch;
use RuntimeException;

/**
 * Local, small-volume inbox. The text file is replaced atomically while a
 * separate, stable lock file serializes readers and writers across processes.
 *
 * @phpstan-type InboxRow array{event_id: string, kind: string, payload: string, received_at: int|float}
 * @phpstan-type ConnectionState array{lease: array{owner: string, fence: int, expires_at: int|float}, cursor: string|null, events: list<InboxRow>}
 * @phpstan-type InboxState array{version: int, connections: array<string, ConnectionState>}
 */
final class FileInboxStore implements InboxStore
{
    private readonly string $path;

    private readonly Closure $now;

    /** The parent directory must already exist on a trusted local filesystem. */
    public function __construct(string $path, ?Closure $now = null)
    {
        if (trim($path) === '' || str_contains($path, "\0") || str_contains($path, '://') || in_array(basename($path), ['.', '..', ''], true)) {
            throw new InvalidArgumentException('File inbox requires a local file path.');
        }
        $directory = realpath(dirname($path));
        if ($directory === false || ! is_dir($directory)) {
            throw new InvalidArgumentException('File inbox parent directory must already exist.');
        }
        $this->path = $directory.DIRECTORY_SEPARATOR.basename($path);
        $this->now = $now ?? static fn (): float => microtime(true);
    }

    /** Creates an empty inbox, or validates existing state without resetting it. */
    public function initialize(): void
    {
        $this->locked(LOCK_EX, function (): void {
            clearstatcache(true, $this->path);
            if (file_exists($this->path) || is_link($this->path)) {
                $this->load();
            } else {
                $this->save(['version' => 1, 'connections' => []]);
            }
        });
    }

    public function acquire(ConnectionId $connection, float $ttl): Lease
    {
        if (! is_finite($ttl) || $ttl <= 0) {
            throw new InvalidArgumentException('Lease TTL must be finite and positive.');
        }

        return $this->mutate(function (array &$state) use ($connection, $ttl): Lease {
            $key = $connection->key();
            $entry = $state['connections'][$key] ?? null;
            $now = ($this->now)();
            if ($entry !== null && $entry['lease']['expires_at'] > $now) {
                throw new LeaseUnavailableException('Another worker owns this connection lease.');
            }
            $previousFence = $entry['lease']['fence'] ?? 0;
            if ($previousFence === PHP_INT_MAX) {
                throw new RuntimeException('File inbox fencing counter is exhausted.');
            }
            $lease = new Lease($connection, bin2hex(random_bytes(16)), $previousFence + 1);
            $state['connections'][$key] = [
                'lease' => ['owner' => $lease->owner, 'fence' => $lease->fence, 'expires_at' => $now + $ttl],
                'cursor' => $entry['cursor'] ?? null,
                'events' => $entry['events'] ?? [],
            ];

            return $lease;
        });
    }

    public function cursor(Lease $lease): ?string
    {
        return $this->locked(LOCK_SH, function () use ($lease): ?string {
            $state = $this->load();
            $entry = $state['connections'][$lease->connection->key()] ?? null;
            $this->assertLease($entry, $lease);

            return $entry['cursor'];
        });
    }

    public function checkpoint(Lease $lease, UpdateBatch $batch): int
    {
        return $this->mutate(function (array &$state) use ($lease, $batch): int {
            $key = $lease->connection->key();
            $entry = $state['connections'][$key] ?? null;
            $this->assertLease($entry, $lease);
            $seen = [];
            foreach ($entry['events'] as $row) {
                $seen['id:'.$row['event_id']] = true;
            }
            $inserted = 0;
            foreach ($batch->events as $event) {
                if (! $event->connection->equals($lease->connection) || $event->id === null || $event->id === '') {
                    throw new InvalidArgumentException('Every persisted event needs a stable provider event ID and the same tenant/connection scope.');
                }
                // Validate even duplicate event payloads, matching the SQL backend.
                $payload = json_encode($event->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $timestamp = ($this->now)();
                if (! isset($seen['id:'.$event->id])) {
                    $entry['events'][] = ['event_id' => $event->id, 'kind' => $event->kind, 'payload' => $payload, 'received_at' => $timestamp];
                    $seen['id:'.$event->id] = true;
                    $inserted++;
                }
            }
            $this->assertLease($entry, $lease);
            $entry['cursor'] = $batch->nextCursor ?? $entry['cursor'];
            $state['connections'][$key] = $entry;

            return $inserted;
        }, $lease);
    }

    public function release(Lease $lease): void
    {
        $this->mutate(function (array &$state) use ($lease): void {
            $key = $lease->connection->key();
            $entry = $state['connections'][$key] ?? null;
            if ($entry !== null && $entry['lease']['owner'] === $lease->owner && $entry['lease']['fence'] === $lease->fence) {
                // Retain the fencing generation across releases and restarts.
                $state['connections'][$key]['lease']['expires_at'] = 0.0;
            }
        });
    }

    /** @return list<array{event_id: string, kind: string, payload: string}> */
    public function events(ConnectionId $connection, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Inbox read limit must be between 1 and 1000.');
        }

        return $this->locked(LOCK_SH, function () use ($connection, $limit): array {
            $state = $this->load();

            return array_map(static fn (array $row): array => ['event_id' => $row['event_id'], 'kind' => $row['kind'], 'payload' => $row['payload']], array_slice($state['connections'][$connection->key()]['events'] ?? [], 0, $limit));
        });
    }

    /**
     * @param  ConnectionState|null  $entry
     *
     * @phpstan-assert ConnectionState $entry
     */
    private function assertLease(?array $entry, Lease $lease): void
    {
        if ($entry === null || $entry['lease']['owner'] !== $lease->owner || $entry['lease']['fence'] !== $lease->fence || $entry['lease']['expires_at'] <= ($this->now)()) {
            throw new LeaseLostException('Worker lease expired or was replaced; the batch was not checkpointed.');
        }
    }

    /**
     * @template T
     *
     * @param  Closure(InboxState): T  $callback
     * @return T
     */
    private function mutate(Closure $callback, ?Lease $lease = null): mixed
    {
        return $this->locked(LOCK_EX, function () use ($callback, $lease): mixed {
            $state = $this->load();
            $result = $callback($state);
            $this->save($state, $lease);

            return $result;
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function locked(int $mode, Closure $callback): mixed
    {
        $lockPath = $this->path.'.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('File inbox lock must not be a symbolic link.');
        }
        // Exclusive creation gives a new lock private permissions without
        // truncating or replacing an inode other processes may already lock.
        $lock = @fopen($lockPath, 'x+b');
        if ($lock !== false) {
            if (! @chmod($lockPath, 0600)) {
                fclose($lock);
                throw new RuntimeException('Could not set private file inbox lock permissions.');
            }
        } else {
            $lock = @fopen($lockPath, 'r+b');
        }
        if ($lock === false) {
            throw new RuntimeException('Could not open file inbox lock.');
        }
        try {
            if (! flock($lock, $mode)) {
                throw new RuntimeException('Could not lock file inbox.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return InboxState */
    private function load(): array
    {
        if (is_link($this->path)) {
            throw new RuntimeException('File inbox data must not be a symbolic link.');
        }
        $content = @file_get_contents($this->path);
        if ($content === false) {
            throw new RuntimeException('File inbox is not initialized or is not readable.');
        }
        try {
            $state = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('File inbox is corrupt; restore a valid backup instead of resetting it.');
        }
        if (! is_array($state) || ($state['version'] ?? null) !== 1 || ! is_array($state['connections'] ?? null)) {
            throw new RuntimeException('File inbox has an invalid or unsupported format.');
        }
        foreach ($state['connections'] as $key => $entry) {
            if (! is_string($key) || ! is_array($entry) || ! is_array($entry['lease'] ?? null)
                || ! is_string($entry['lease']['owner'] ?? null) || ! preg_match('/\A[a-f0-9]{32}\z/D', $entry['lease']['owner'])
                || ! is_int($entry['lease']['fence'] ?? null) || $entry['lease']['fence'] < 1
                || ! $this->validTimestamp($entry['lease']['expires_at'] ?? null)
                || ! array_key_exists('cursor', $entry) || ($entry['cursor'] !== null && ! is_string($entry['cursor']))
                || ! is_array($entry['events'] ?? null) || ! array_is_list($entry['events'])) {
                throw new RuntimeException('File inbox contains an invalid connection checkpoint.');
            }
            $seen = [];
            foreach ($entry['events'] as $row) {
                if (! is_array($row) || ! is_string($row['event_id'] ?? null) || $row['event_id'] === ''
                    || isset($seen['id:'.$row['event_id']]) || ! is_string($row['kind'] ?? null) || ! is_string($row['payload'] ?? null)
                    || ! $this->validTimestamp($row['received_at'] ?? null)) {
                    throw new RuntimeException('File inbox contains an invalid event.');
                }
                $seen['id:'.$row['event_id']] = true;
                try {
                    json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new RuntimeException('File inbox contains a corrupt event payload.');
                }
            }
        }

        /** @var InboxState $state */
        return $state;
    }

    private function validTimestamp(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    /** @param InboxState $state */
    private function save(array $state, ?Lease $lease = null): void
    {
        $content = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL;
        $directory = dirname($this->path);
        // tempnam creates mode 0600. Keep the temporary file on the same
        // filesystem so a successful rename commits the complete snapshot.
        $temporary = @tempnam($directory, '.payambridge-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create file inbox checkpoint.');
        }
        $stream = null;
        try {
            if (dirname($temporary) !== $directory || ($stream = @fopen($temporary, 'wb')) === false) {
                throw new RuntimeException('Could not open file inbox checkpoint.');
            }
            $offset = 0;
            $length = strlen($content);
            while ($offset < $length) {
                $written = fwrite($stream, substr($content, $offset, 8192));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Could not write complete file inbox checkpoint.');
                }
                $offset += $written;
            }
            if (! fflush($stream) || ! fsync($stream)) {
                throw new RuntimeException('Could not flush file inbox checkpoint.');
            }
            fclose($stream);
            $stream = null;
            // Serialization and disk I/O can outlast the lease, too.
            if ($lease !== null) {
                $this->assertLease($state['connections'][$lease->connection->key()] ?? null, $lease);
            }
            if (is_link($this->path) || ! @rename($temporary, $this->path)) {
                throw new RuntimeException('Could not commit file inbox checkpoint.');
            }
            $this->syncDirectory($directory);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function syncDirectory(string $directory): void
    {
        // Unix needs the directory entry synced as well as the file contents
        // for the rename to survive a machine restart. Windows has no portable
        // directory stream equivalent; only atomic process-crash recovery is
        // claimed there.
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }
        $stream = @fopen($directory, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open file inbox directory for synchronization.');
        }
        try {
            if (! @fsync($stream)) {
                throw new RuntimeException('Could not synchronize file inbox directory; checkpoint outcome must be checked.');
            }
        } finally {
            fclose($stream);
        }
    }
}
