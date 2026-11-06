<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Console\Kernel;
use PayamBridge\Contracts\ReceivesUpdates;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Laravel\ConnectionManager;
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\InboxStore;
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;
use PayamBridge\Testing\FakeHttpClient;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$driver = $argv[1] ?? '';
$mode = $argv[2] ?? '';
ensure(in_array($driver, ['sqlite', 'file'], true) && in_array($mode, ['first', 'replay'], true), 'Expected an inbox driver and first/replay mode.');
ensure(config('payambridge.inbox.driver') === $driver, 'Inbox driver did not survive config cache.');
$otherPath = config('payambridge.inbox.paths.'.($driver === 'file' ? 'sqlite' : 'file'));
$otherHash = is_file($otherPath) ? hash_file('sha256', $otherPath) : null;
$fake = new FakeHttpClient([new Response(200, [], '{"ok":true,"result":[{"update_id":1,"future_event":{"text":"سلام از صندوق ورودی"}}]}')]);
$app->instance(HttpTransport::class, new HttpTransport($fake));
$connection = $app->make(ConnectionManager::class)->connection('bale');
ensure($connection instanceof ReceivesUpdates, 'Expected an update-capable Bale connection.');
$store = $app->make(InboxStore::class);
ensure($driver === 'file' ? $store instanceof FileInboxStore : $store instanceof SqliteInboxStore, 'Wrong inbox backend resolved.');
$expectedCount = $mode === 'first' ? 1 : 0;
ensure($app->make(Poller::class)->once($connection) === $expectedCount, 'Inbox checkpoint or replay deduplication failed.');
$payload = json_decode((string) $fake->requests()[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
ensure($payload === ($mode === 'first' ? ['limit' => 100] : ['limit' => 100, 'offset' => 2]), 'Poller did not resume from the durable cursor.');
$rows = $store->events($connection->id());
ensure(count($rows) === 1 && $rows[0]['event_id'] === '1', 'Expected exactly one durable event.');
ensure(json_decode($rows[0]['payload'], true, flags: JSON_THROW_ON_ERROR)['future_event']['text'] === 'سلام از صندوق ورودی', 'Stored UTF-8 payload changed.');
clearstatcache();
ensure((is_file($otherPath) ? hash_file('sha256', $otherPath) : null) === $otherHash, 'Selecting one backend modified the other backend.');
echo "Real Laravel inbox {$driver}: {$mode} passed with cached config.\n";
