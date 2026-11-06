# PayamBridge Laravel bridge

Laravel integration for the separate payambridge/sdk package. PHP 8.3–8.5 and Illuminate/Laravel 12–13 are declared. This package depends on payambridge/sdk:^0.1. Install with composer require payambridge/laravel:^0.1 **after both packages are publicly published**; before that, use local path repositories for both packages with version 0.1.0 (see the repository's docs/installation.md).

The auto-discovered provider registers scoped ConnectionManager, ConnectionFactory, and HttpTransport bindings. Optional facade: PayamBridge\Laravel\Facades\PayamBridge. Publish config with:

~~~sh
php artisan vendor:publish --tag=payambridge-config
php artisan payam:capabilities bale
~~~

Config defines named bale Bot, rubika Bot, and eitaa-app App connections, plus HTTP timeouts. token_env contains an environment variable **name** such as PAYAMBRIDGE_BALE_TOKEN. With config:cache, put the credential in the actual worker/FPM environment or bind a custom PayamBridge\Laravel\CredentialResolver. Each manager call resolves a new immutable scoped connection and current credential. Authorize tenant access to connection names in the host application.

For durable Bale polling, published `payambridge.inbox.driver` defaults to `sqlite`; set `PAYAMBRIDGE_INBOX_DRIVER=file` before caching config, or set the config value to `file`, for a TXT file without `ext-pdo_sqlite`. Default paths are `storage_path('app/private/payambridge/inbox.sqlite')` and `storage_path('app/private/payambridge/inbox.txt')`; the lease defaults to 60 seconds. The bridge lazily binds `PayamBridge\Runtime\InboxStore` and `PayamBridge\Runtime\Poller`. Resolving them initializes the selected store and creates its private parent directory if needed. The TXT backend suits small local workloads and rewrites full JSON state at each checkpoint; choose SQLite for larger backlogs. Keep the TXT data and adjacent `.lock` file on the same trusted local filesystem outside the web root, and do not manually edit or delete them while workers run. Switching backends does not migrate events or cursor. Schedule and supervise polling in the host application.

~~~php
use Illuminate\Notifications\Notification;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class OrderRegistered extends Notification
{
    public function via(object $notifiable): array { return [PayamBridgeChannel::class]; }
    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('Order received');
    }
}
~~~

~~~php
use Illuminate\Support\Facades\Notification;
use PayamBridge\Laravel\Notifications\NotificationRoute;

Notification::route('payambridge', new NotificationRoute('bale', '123456'))
    ->notify(new OrderRegistered());
~~~

A notifiable may instead implement routeNotificationForPayambridge and return NotificationRoute, or null to skip. For queued notifications, resolve connections inside the worker; Credential cannot be serialized. Dispatch after database commit when the message depends on committed data. If a write raises AmbiguousDeliveryException, delivery may have succeeded; blind job retries may duplicate it.

No routes, migrations, webhooks, or daemons are installed. The capabilities command lists local implemented capabilities without a network request, but needs the configured credential. Durable polling is only available for Bale via the core Poller and either SQLite or TXT inbox. No valid-credential live request is claimed by these docs. See the repository's README, docs/laravel.md, docs/api.md, and docs/IMPLEMENTATION_STATUS.md. MIT.
