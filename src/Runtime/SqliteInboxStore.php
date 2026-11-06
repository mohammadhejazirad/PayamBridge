<?php

declare(strict_types=1);

namespace PayamBridge\Runtime;

use Closure;
use InvalidArgumentException;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Data\UpdateBatch;
use PDO;
use Throwable;

final class SqliteInboxStore implements InboxStore
{
    private Closure $now;

    /** The PDO connection is exclusively owned by this store; schema setup is explicit. */
    public function __construct(private readonly PDO $pdo, ?Closure $now = null)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new InvalidArgumentException('SqliteInboxStore requires a SQLite PDO connection.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $this->now = $now ?? static fn (): float => microtime(true);
    }

    public function initialize(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS payambridge_leases (connection TEXT PRIMARY KEY, owner TEXT NOT NULL, fence INTEGER NOT NULL, expires_at REAL NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS payambridge_cursors (connection TEXT PRIMARY KEY, cursor TEXT)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS payambridge_inbox (connection TEXT NOT NULL, event_id TEXT NOT NULL, kind TEXT NOT NULL, payload TEXT NOT NULL, received_at REAL NOT NULL, PRIMARY KEY(connection, event_id))');
    }

    public function acquire(ConnectionId $connection, float $ttl): Lease
    {
        if (! is_finite($ttl) || $ttl <= 0) {
            throw new InvalidArgumentException('Lease TTL must be finite and positive.');
        }

        return $this->transaction(function () use ($connection, $ttl): Lease {
            $statement = $this->pdo->prepare('SELECT fence, expires_at FROM payambridge_leases WHERE connection = ?');
            $statement->execute([$connection->key()]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $now = ($this->now)();
            if ($row !== false && (float) $row['expires_at'] > $now) {
                throw new LeaseUnavailableException('Another worker owns this connection lease.');
            }
            $fence = $row === false ? 1 : (int) $row['fence'] + 1;
            $owner = bin2hex(random_bytes(16));
            $statement = $this->pdo->prepare('INSERT INTO payambridge_leases (connection, owner, fence, expires_at) VALUES (?, ?, ?, ?) ON CONFLICT(connection) DO UPDATE SET owner=excluded.owner, fence=excluded.fence, expires_at=excluded.expires_at');
            $statement->execute([$connection->key(), $owner, $fence, $now + $ttl]);

            return new Lease($connection, $owner, $fence);
        });
    }

    public function cursor(Lease $lease): ?string
    {
        $this->assertLease($lease);
        $statement = $this->pdo->prepare('SELECT cursor FROM payambridge_cursors WHERE connection = ?');
        $statement->execute([$lease->connection->key()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false || $row['cursor'] === null ? null : (string) $row['cursor'];
    }

    public function checkpoint(Lease $lease, UpdateBatch $batch): int
    {
        return $this->transaction(function () use ($lease, $batch): int {
            $this->assertLease($lease);
            $inserted = 0;
            $statement = $this->pdo->prepare('INSERT INTO payambridge_inbox (connection, event_id, kind, payload, received_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(connection, event_id) DO NOTHING');
            foreach ($batch->events as $event) {
                if (! $event->connection->equals($lease->connection) || $event->id === null || $event->id === '') {
                    throw new InvalidArgumentException('Every persisted event needs a stable provider event ID and the same tenant/connection scope.');
                }
                $payload = json_encode($event->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $statement->execute([$lease->connection->key(), $event->id, $event->kind, $payload, ($this->now)()]);
                $inserted += $statement->rowCount();
            }
            // Recheck expiry after persistence; no cursor is committed by a stale worker.
            $this->assertLease($lease);
            // A batch without a successor cannot erase an already durable cursor.
            $statement = $this->pdo->prepare('INSERT INTO payambridge_cursors (connection, cursor) VALUES (?, ?) ON CONFLICT(connection) DO UPDATE SET cursor=COALESCE(excluded.cursor, payambridge_cursors.cursor)');
            $statement->execute([$lease->connection->key(), $batch->nextCursor]);

            return $inserted;
        });
    }

    public function release(Lease $lease): void
    {
        // Keep the row so fencing tokens increase across releases/restarts (no ABA).
        $statement = $this->pdo->prepare('UPDATE payambridge_leases SET expires_at = 0 WHERE connection = ? AND owner = ? AND fence = ?');
        $statement->execute([$lease->connection->key(), $lease->owner, $lease->fence]);
    }

    /** @return list<array{event_id: string, kind: string, payload: string}> */
    public function events(ConnectionId $connection, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Inbox read limit must be between 1 and 1000.');
        }
        $statement = $this->pdo->prepare('SELECT event_id, kind, payload FROM payambridge_inbox WHERE connection = ? ORDER BY rowid LIMIT ?');
        $statement->bindValue(1, $connection->key());
        $statement->bindValue(2, $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertLease(Lease $lease): void
    {
        $statement = $this->pdo->prepare('SELECT owner, fence, expires_at FROM payambridge_leases WHERE connection = ?');
        $statement->execute([$lease->connection->key()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $row['owner'] !== $lease->owner || (int) $row['fence'] !== $lease->fence || (float) $row['expires_at'] <= ($this->now)()) {
            throw new LeaseLostException('Worker lease expired or was replaced; the batch was not checkpointed.');
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function transaction(Closure $callback): mixed
    {
        // BEGIN IMMEDIATE obtains SQLite's writer lock before reading the fencing row.
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $callback();
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $exception) {
            $this->pdo->exec('ROLLBACK');
            throw $exception;
        }
    }
}
