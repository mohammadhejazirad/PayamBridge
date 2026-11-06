# PayamBridge

This project was developed using AI agents.

## Contents

- [Current capabilities](#current-capabilities)
- [Requirements and installation](#requirements-and-installation)
- [Send from standalone PHP](#send-from-standalone-php)
- [Receive updates](#receive-updates)
- [Durable Bale inbox](#durable-bale-inbox)
- [Laravel configuration](#laravel-configuration)
- [Notifications and queues](#notifications-and-queues)
- [Errors and security](#errors-and-security)
- [Deployment and troubleshooting](#deployment-and-troubleshooting)
- [Contributing and releases](#contributing-and-releases)

A capability-based SDK for connecting PHP applications to Iranian messengers, with a separate Laravel bridge. The core requires no Laravel, Redis, Node, or database. [فارسی](README.md).

**Status:** The initial release is being prepared. Public publication and a successful request with valid live credentials are not verified unless recorded in the [implementation report](docs/IMPLEMENTATION_STATUS.md). Both packages constrain PHP to **8.3, 8.4, and 8.5**; the bridge supports **Laravel 12 and 13**. Consult the report and CI for actual matrix results.

## Current capabilities

| Connection | Send text | Receive updates | Bot identity | Durable inbox |
| --- | --- | --- | --- | --- |
| Bale Bot | Yes | Yes | Yes | SQLite or TXT file |
| Rubika Bot | Yes | Yes, string cursor | Yes | No |
| Eitaa App | Yes, to an authorized user | No | No | No |

This table lists **implemented operations**. Eitaa App differs from EitaaYar channel publishing and personal accounts. Media, webhooks, Mini App, Bale Safir, EitaaYar, personal accounts, outbox/dispatcher, and AI responder are not yet provided. See the [API reference](docs/api.md).

## Requirements and installation

Both packages require **PHP 8.3, 8.4, or 8.5**, `ext-json`, `ext-mbstring`, and **Composer 2**. The bridge supports **Laravel 12 and 13**. `ext-pdo_sqlite` is needed only for the optional SQLite inbox; the TXT file inbox does not need it. Sending needs no database.

After version 0.1.0 of **both** packages is published and indexed by Packagist:

~~~sh
composer require payambridge/sdk:^0.1
# In a Laravel app, install the bridge instead; it requires the SDK:
composer require payambridge/laravel:^0.1
php artisan vendor:publish --tag=payambridge-config
~~~

Before publication, add path repositories to the **consumer application's** `composer.json`, substituting your checkout's absolute path:

~~~json
{
  "repositories": [
    {"type": "path", "url": "/absolute/path/PayamBridge", "options": {"versions": {"payambridge/sdk": "0.1.0"}, "symlink": false}},
    {"type": "path", "url": "/absolute/path/PayamBridge/packages/laravel", "options": {"versions": {"payambridge/laravel": "0.1.0"}, "symlink": false}}
  ],
  "require": {"payambridge/laravel": "^0.1"}
}
~~~

Merge these entries into the application's existing manifest rather than replacing it. Run `composer require payambridge/laravel:^0.1 --with-all-dependencies`. For standalone PHP, retain only the SDK repository and run `composer require payambridge/sdk:^0.1`. Commit the consumer application's lock file. The bridge is a separate distribution, not included in the SDK archive. The [installation guide](docs/installation.md) has packaging details.

For this checkout:

~~~sh
composer install
composer check
~~~

These run Composer validation, Pint, PHPStan, PHPUnit, inventory, and distribution archive validation. Development checks require `pdo_sqlite` and `zip`; sending messages from a consumer requires neither.

Obtain a credential for the matching identity using the provider's instructions: [Bale Bot](https://docs.bale.ai/), [Rubika Bot](https://rubika.ir/botapi), or [Eitaa App messaging](https://developer.eitaa.com/docs/Develop/SendMassage/). Bot and App credentials are not interchangeable. The SDK does not create accounts or grant recipient permissions.

## Send from standalone PHP

~~~php
<?php

require 'vendor/autoload.php';

use PayamBridge\ConnectionFactory;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\SendsText;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;

$id = new ConnectionId(Provider::Bale, Surface::Bot, 'support', 'shop-a');
$token = getenv('BALE_BOT_TOKEN');
if (! is_string($token) || $token === '') {
    throw new RuntimeException('BALE_BOT_TOKEN is required.');
}
$connection = (new ConnectionFactory(HttpTransport::createDefault()))
    ->create($id, new Credential($token));
if ($connection instanceof SendsText) {
    $receipt = $connection->sendText(new ChatRef($id, '123456'), 'Hello from PayamBridge');
    echo $receipt->message?->id ?? 'sent';
}
~~~

This sends a real message: use your own credential and authorized destination. The [executable example](examples/send.php) checks environment inputs. ConnectionId includes provider, identity surface, connection name, and tenant. A ChatRef with the wrong scope is rejected before HTTP; the host must still authorize tenant access to named connections. Eitaa App has no returned message ID, so MessageReceipt::message is null.

For other drivers, select `Provider::Rubika`/`Surface::Bot` or `Provider::Eitaa`/`Surface::App`. Eitaa App requires a positive integer chat ID and recipient permission. Connection names and tenants accept 1–128 ASCII letters, digits, periods, underscores, or hyphens. The shared contracts are `SendsText::sendText(ChatRef, string): MessageReceipt`, `GetsIdentity::getMe(): BotIdentity`, and `ReceivesUpdates::fetchUpdates(?string): UpdateBatch`. Every connection exposes `id()` and `capabilities()`; use `capabilities()->supports(Capability::SendsText)` or `require(...)` when an operation is optional.

## Receive updates

BaleBot::getUpdates(offset, limit, timeout) and RubikaBot::getUpdates(offsetId, limit) expose native reads. ReceivesUpdates::fetchUpdates(cursor) supports periodic polling. Preserve Rubika cursors as opaque strings; its events have no documented stable event ID.

For direct reads, persist `UpdateBatch::nextCursor` only after handling `UpdateBatch::events`. Each `IncomingEvent` has kind, optional chat/message/text, and raw provider data. Unknown fields remain in `raw`:

~~~php
use PayamBridge\Contracts\ReceivesUpdates;

$savedCursor = null; // Load your previously committed cursor, if any.
if ($connection instanceof ReceivesUpdates) {
    $batch = $connection->fetchUpdates($savedCursor); // null for the first read
    foreach ($batch->events as $event) {
        // Handle or persist the event before saving the successor cursor.
    }
    $savedCursor = $batch->nextCursor;
}
~~~

Bale's next cursor is the greatest update ID plus one. Its offset acknowledges earlier updates at the provider. For native Bale long polling, set read/total HTTP timeouts longer than the requested provider timeout.

## Durable Bale inbox

To collect **Bale** updates durably, choose a store:

~~~php
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;

// SQLite: useful for a larger backlog; requires ext-pdo_sqlite.
$inbox = new SqliteInboxStore(new PDO('sqlite:/private/storage/payambridge.sqlite'));

// Or a TXT file: suitable for small, local workloads; no SQLite extension.
// To choose the file store, use this assignment instead of the SQLite one:
// $inbox = new FileInboxStore('/private/storage/payambridge/inbox.txt');

$inbox->initialize(); // Explicitly prepare the selected store.
$count = (new Poller($inbox))->once($connection);
$rows = $inbox->events($id, 100);
~~~

Here connection must be Bale. Each `once()` saves one batch and cursor under a lease and deduplicates stable IDs. SQLite commits them in a transaction; the file store writes UTF-8 JSON state to the `.txt` file by atomic same-directory rename while holding an exclusive lock on a stable adjacent `.lock` file. The TXT store rewrites its full state at each checkpoint. Its parent directory must already exist for standalone use. Keep the data and lock files together on the same trusted local filesystem outside the web root; do not manually edit or delete either file while workers are running. Select SQLite for a larger backlog. Changing stores does not migrate existing events or cursor automatically. Poller throws UnsupportedCapabilityException for Rubika. The inbox has no business handler, processed acknowledgment, daemon, or outbox. Define a backup and retention policy. An advanced Bale offset acknowledges earlier updates on the server; persist first.

Run `once()` from a scheduler or supervised worker in your application. Lease contention raises `LeaseUnavailableException`; an expired or replaced lease raises `LeaseLostException` without committing a cursor. The returned count is newly inserted events; `events()` returns raw JSON payload rows, not a business processing queue. Track processed rows, backups, and retention in the host application. Avoid simultaneous webhook and polling consumption without a deliberate transition plan.

## Laravel configuration

The bridge in packages/laravel is a separate package with an auto-discovered service provider:

~~~sh
php artisan vendor:publish --tag=payambridge-config
php artisan payam:capabilities bale
~~~

The capabilities command prints local capabilities without a network request, but constructing a connection requires its configured credential. Config stores environment variable names such as PAYAMBRIDGE_BALE_TOKEN, not secrets. With config:cache, set secrets in the actual PHP-FPM/worker environment or bind CredentialResolver to a secret store.

For durable Bale polling, `payambridge.inbox.driver` defaults to `sqlite`. Set `PAYAMBRIDGE_INBOX_DRIVER=file` before caching config, or choose `file` in the published config, to use the TXT backend without `ext-pdo_sqlite`. The default paths are `storage_path('app/private/payambridge/inbox.sqlite')` and `storage_path('app/private/payambridge/inbox.txt')`; `inbox.lease_seconds` defaults to `60.0`. The bridge lazily initializes the selected store and creates its private parent directory on resolution. Inject `PayamBridge\Runtime\InboxStore` or `PayamBridge\Runtime\Poller`; schedule and supervise your own polling worker. See [Laravel inbox setup](docs/laravel.md#durable-bale-inbox).

Published `config/payambridge.php` defines default connection `bale`, plus `bale` Bot, `rubika` Bot, and `eitaa-app` App. Each entry has `provider`, `surface`, `tenant`, and `token_env`. The other default secret references are `PAYAMBRIDGE_RUBIKA_TOKEN` and `PAYAMBRIDGE_EITAA_APP_TOKEN`. The default connect/read/total timeouts are 5/30/35 seconds. A tenant-specific entry looks like:

~~~php
// In config/payambridge.php in your application:
'connections' => [
    'shop-a-bale' => [
        'provider' => 'bale',
        'surface' => 'bot',
        'tenant' => 'shop-a',
        'token_env' => 'SHOP_A_BALE_TOKEN',
    ],
],
~~~

~~~php
use PayamBridge\Contracts\SendsText;
use PayamBridge\Data\ChatRef;
use PayamBridge\Laravel\ConnectionManager;

$connection = app(ConnectionManager::class)->connection('bale');
if ($connection instanceof SendsText) {
    $connection->sendText(new ChatRef($connection->id(), '123456'), 'Order received');
}
~~~

PayamBridgeChannel, TextMessage, and NotificationRoute integrate with Laravel notifications. See the [Laravel guide](docs/laravel.md) for queue, route, and transaction examples. Resolve the connection in the job; Credential cannot be serialized. The bridge installs no route, migration, or daemon.

The manager resolves a fresh immutable connection and current credential on each call. It does not authorize a tenant's access to a connection name; enforce that in the application. The bridge also installs no polling command or webhook handler.

## Notifications and queues

Return `TextMessage` from a notification and route it through `PayamBridgeChannel`:

~~~php
use Illuminate\Notifications\Notification;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class OrderRegistered extends Notification
{
    public function via(object $notifiable): array
    {
        return [PayamBridgeChannel::class];
    }

    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('Your order was received');
    }
}
~~~

~~~php
use Illuminate\Support\Facades\Notification;
use PayamBridge\Laravel\Notifications\NotificationRoute;

Notification::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify(new OrderRegistered());
~~~

A normal notifiable can return `NotificationRoute` from `routeNotificationForPayambridge`, or `null` to skip delivery. A route needs a nonempty connection name and chat ID; `toPayamBridge` must return nonempty UTF-8 text in `TextMessage`. The channel returns `MessageReceipt` for a send or `null` for a skipped route. For queued delivery, implement Laravel's `ShouldQueue` and use `Queueable`. Resolve the connection in the worker, dispatch after database commit when needed, and restart long-running workers after secret/config changes. Configure queue failure and reconciliation behavior for uncertain writes; automatic job retries can duplicate them.

For a queued version of the example, use:

~~~php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use PayamBridge\Laravel\Notifications\NotificationRoute;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class QueuedOrderRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array { return [PayamBridgeChannel::class]; }
    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('Your order was received');
    }
}

NotificationFacade::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify((new QueuedOrderRegistered())->afterCommit());
~~~

## Errors and security

SdkException exposes kind, context, and optional providerCode and retryAfter. Kinds are transport, timeout, provider, authorization, permission, validation, rate_limit, protocol_mismatch, and delivery_unknown. Invalid local input normally throws InvalidArgumentException; absent capabilities throw UnsupportedCapabilityException.

For **writes**, a network failure, 5xx, or malformed success response may mean the provider accepted the message but the response was lost. Hold AmbiguousDeliveryException for reconciliation or manual review. The SDK does not retry automatically; blindly retrying a queued job can duplicate a message. MessageReceipt::raw, IncomingEvent::raw, and fake requests can contain sensitive data. SDK exception text and logs omit credentials and payloads. The default transport verifies TLS and rejects redirects; custom PSR-18 clients need suitable timeouts and security settings.

~~~php
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Exceptions\SdkException;

try {
    $receipt = $connection->sendText($chat, $text);
} catch (AmbiguousDeliveryException $e) {
    // Delivery may have succeeded. Reconcile or review before retrying.
} catch (SdkException $e) {
    // Inspect $e->kind, $e->context, $e->providerCode, $e->retryAfter.
}
~~~

For a custom standalone timeout or PSR-3 logger:

~~~php
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\Timeouts;

$transport = HttpTransport::createDefault(new Timeouts(connect: 5, read: 60, total: 65));
// Optionally pass logger: $yourPsrLogger to createDefault().
~~~

Tests can inject `new HttpTransport(new PayamBridge\Testing\FakeHttpClient([...]))` with explicit PSR-7 responses. In Laravel, bind that transport in the container before resolving a connection; also supply a test credential or a test `CredentialResolver`. The fake makes no network requests and throws if responses run out. The clean application checks in `tools/consumer/` show a full notification and database queue example.

## Deployment and troubleshooting

- **Missing credential:** set the `token_env` variable in the actual PHP-FPM and queue-worker process environment. `config:cache` may prevent runtime `.env` loading. Alternatively bind `PayamBridge\Laravel\CredentialResolver` to a secret-store implementation; never commit tokens in config or jobs.
- **Unknown connection or wrong scope:** check the published name, provider/surface, tenant, and `ChatRef`'s `ConnectionId`; authorize selected names in the host application.
- **Unsupported capability:** inspect `capabilities()` and the matrix above. Eitaa App cannot read updates; Rubika cannot use the durable poller.
- **Timeout or rate limit:** inspect `kind` and `retryAfter` when available; define application-specific retry policy. For Bale native long polling, set read/total timeouts above the provider timeout.
- **Queue duplicate risk:** review `delivery_unknown` before retrying. There is no persistent outbox or idempotency-key abstraction in this release.

The default transport caps response bodies at 4 MiB. Custom PSR-18 clients need an equivalent TLS, redirect, and timeout policy. Store inbox data and lock files on private persistent storage and define backup and retention.

## Contributing and releases

Run `composer check` for changes. `composer test:repeat` repeats the test suite; `composer test:consumer` exercises clean PHP and Laravel 12/13 consumers when dependencies are available. Fixture, contract, and consumer tests do not replace a live test with valid credentials. See [implementation status](docs/IMPLEMENTATION_STATUS.md) for actual results, the [API](docs/api.md), [sources](research/sources.json), [open discrepancies](research/doc-conflicts.md), [changelog](CHANGELOG.md), and [release process](docs/releasing.md). MIT license.

`composer test:archive` inspects both distribution archives and checks that dependencies, caches, environment files, and the Laravel bridge do not leak into the standalone SDK. `composer check` includes this validation. Development checks require `pdo_sqlite` and `zip`; these are not mandatory for sending messages from a consumer.
