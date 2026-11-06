<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\InboxStore;
use PayamBridge\Runtime\SqliteInboxStore;
use PDO;
use RuntimeException;

final class InboxStoreFactory
{
    public static function make(Application $app): InboxStore
    {
        $config = $app->make('config');
        $driver = $config->get('payambridge.inbox.driver');
        if ($driver !== 'sqlite' && $driver !== 'file') {
            throw new InvalidArgumentException('payambridge.inbox.driver must be "sqlite" or "file".');
        }

        $path = $config->get('payambridge.inbox.paths.'.$driver);
        if (! is_string($path) || trim($path) === '' || str_contains($path, "\0") || str_contains($path, '://')) {
            throw new InvalidArgumentException('payambridge.inbox.paths.'.$driver.' must be a non-empty local file path.');
        }

        self::ensureParentDirectory($path);

        if ($driver === 'file') {
            $store = new FileInboxStore($path);
        } else {
            $newFile = ! file_exists($path);
            $store = new SqliteInboxStore(new PDO('sqlite:'.$path));
            if ($newFile && is_file($path) && ! chmod($path, 0600)) {
                throw new RuntimeException('Unable to set private permissions on the SQLite inbox.');
            }
        }

        $store->initialize();

        return $store;
    }

    private static function ensureParentDirectory(string $path): void
    {
        $directory = dirname($path);
        if (is_dir($directory)) {
            return;
        }
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create inbox directory: '.$directory);
        }
    }
}
