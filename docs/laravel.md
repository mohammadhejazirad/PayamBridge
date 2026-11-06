# Laravel bridge

Install payambridge/laravel:^0.1 (or [both local path packages](installation.md) before publication). The bridge supports Laravel 12/13 on PHP 8.3–8.5. Its provider is auto-discovered. It registers scoped HttpTransport, ConnectionFactory, and ConnectionManager bindings, a replaceable CredentialResolver, an optional facade, a notification channel, and one local capabilities command. It installs no routes, migrations, poller scheduler, daemon, or webhook handler.

## Configure named connections

~~~sh
php artisan vendor:publish --tag=payambridge-config
~~~

The published config/payambridge.php has a default name and connections for bale (Bot), rubika (Bot), and eitaa-app (App). Each definition has provider, surface, tenant, and token_env. token_env is an **environment variable name**, not the token value. The initial names are PAYAMBRIDGE_BALE_TOKEN, PAYAMBRIDGE_RUBIKA_TOKEN, and PAYAMBRIDGE_EITAA_APP_TOKEN. The timeouts array has connect, read, and total seconds (defaults 5, 30, and 35). The optional `inbox` section selects the durable Bale polling store.

~~~php
// config/payambridge.php, in the consuming application
'connections' => [
    'shop-a-bale' => [
        'provider' => 'bale',
        'surface' => 'bot',
        'tenant' => 'shop-a',
        'token_env' => 'SHOP_A_BALE_TOKEN',
    ],
],
~~~

The manager creates a fresh immutable driver each time. The application must authorize which named connection a tenant may request; the SDK scope check alone is not an access-control system. With config:cache, Laravel may not load .env at runtime. Set the referenced value in the actual worker/FPM environment or bind PayamBridge\Laravel\CredentialResolver to a vault-backed implementation before use. Never serialize Credential or a connection into a job payload; resolve it in the job handler.

~~~php
use PayamBridge\Contracts\SendsText;
use PayamBridge\Data\ChatRef;
use PayamBridge\Laravel\ConnectionManager;

$connection = app(ConnectionManager::class)->connection('shop-a-bale');
if ($connection instanceof SendsText) {
    $connection->sendText(new ChatRef($connection->id(), '123456'), 'Order received');
}
~~~

## Notifications

A notification can return TextMessage from toPayamBridge. Route it through PayamBridgeChannel without changing your User model:

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
        return new TextMessage('سفارش شما ثبت شد');
    }
}
~~~

~~~php
use Illuminate\Support\Facades\Notification;
use PayamBridge\Laravel\Notifications\NotificationRoute;

Notification::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify(new OrderRegistered());
~~~

Alternatively, a normal notifiable may implement routeNotificationForPayambridge and return NotificationRoute; return null to skip. A route must have a nonempty connection name and chat ID. toPayamBridge must return TextMessage. TextMessage checks nonempty UTF-8 text before sending. The channel returns the MessageReceipt from a send, or null for a skipped route.

To queue it, implement ShouldQueue and use Laravel's Queueable trait on the notification. For notifications depending on a database transaction, dispatch after commit or use Laravel's after-commit mechanism. A queue worker can retry after a successful send whose response was lost. Treat AmbiguousDeliveryException (delivery_unknown) as a reconciliation/manual-review outcome; configure the consumer's failure policy instead of blind retries. The package has no persistent outbox yet.

## Durable Bale inbox

The published `payambridge.inbox.driver` defaults to `sqlite`; set `PAYAMBRIDGE_INBOX_DRIVER=file` or set the published config value to `file` to use a TXT file without `ext-pdo_sqlite`. The supported values are `sqlite` and `file`. The defaults are `storage_path('app/private/payambridge/inbox.sqlite')` for `inbox.paths.sqlite`, `storage_path('app/private/payambridge/inbox.txt')` for `inbox.paths.file`, and `60.0` seconds for `inbox.lease_seconds`. Change these paths in the published config if needed. Set the environment variable before caching config; restart long-running workers after changing it.

~~~php
// Selected entries in config/payambridge.php:
'inbox' => [
    'driver' => env('PAYAMBRIDGE_INBOX_DRIVER', 'sqlite'),
    'paths' => [
        'sqlite' => storage_path('app/private/payambridge/inbox.sqlite'),
        'file' => storage_path('app/private/payambridge/inbox.txt'),
    ],
    'lease_seconds' => 60.0,
],
~~~

The bridge binds `InboxStore` and `Poller` for injection. Resolving either lazily creates the selected store's private parent directory if needed and initializes that store. Provider registration and boot do not create inbox files. Schedule and supervise your own worker; only Bale supports durable collection.

~~~php
use PayamBridge\Laravel\ConnectionManager;
use PayamBridge\Runtime\InboxStore;
use PayamBridge\Runtime\Poller;

$connection = app(ConnectionManager::class)->connection('bale');
$inbox = app(InboxStore::class);
$count = app(Poller::class)->once($connection);
~~~

For reading rows, check whether the resolved store is a `SqliteInboxStore` or `FileInboxStore`, then call its `events()` method; that method is not on `InboxStore`. The file backend stores UTF-8 JSON in the `.txt` file, holds a stable adjacent `.lock`, and atomically renames a same-directory checkpoint under that lock. Keep both files together on the same trusted local filesystem outside the web root. Do not manually edit or delete them while workers run. Each file checkpoint rewrites full state; choose SQLite for larger backlogs. Switching drivers does not migrate existing cursor or events.

## Local command and workers

~~~sh
php artisan payam:capabilities bale
~~~

This prints connection scope and implemented capabilities without making a provider API request. It still resolves the configured credential to construct the driver. No doctor, webhook, session, or polling Artisan command exists. Configure the worker's process environment and restart long-running workers after configuration changes.

See [API reference](api.md) and [implementation status](IMPLEMENTATION_STATUS.md) for exact coverage.
