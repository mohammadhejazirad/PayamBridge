# Laravel bridge

Install payambridge/laravel:^0.1 (or [both local path packages](installation.md) before publication). The bridge supports Laravel 12/13 on PHP 8.3–8.5. Its provider is auto-discovered. It registers scoped HttpTransport, ConnectionFactory, and ConnectionManager bindings, a replaceable CredentialResolver, an optional facade, a notification channel, and one local capabilities command. It installs no routes, migrations, poller scheduler, daemon, or webhook handler.

## Configure named connections

~~~sh
php artisan vendor:publish --tag=payambridge-config
~~~

The published config/payambridge.php has a default name and connections for bale (Bot), rubika (Bot), and eitaa-app (App). Each definition has provider, surface, tenant, and token_env. token_env is an **environment variable name**, not the token value. The initial names are PAYAMBRIDGE_BALE_TOKEN, PAYAMBRIDGE_RUBIKA_TOKEN, and PAYAMBRIDGE_EITAA_APP_TOKEN. The timeouts array has connect, read, and total seconds (defaults 5, 30, and 35).

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

## Local command and workers

~~~sh
php artisan payam:capabilities bale
~~~

This prints connection scope and implemented capabilities without making a provider API request. It still resolves the configured credential to construct the driver. No doctor, webhook, session, or polling Artisan command exists. If you use the core Poller, schedule and supervise it in your application; currently durable collection is supported only for Bale. Configure the worker's process environment and restart long-running workers after configuration changes.

See [API reference](api.md) and [implementation status](IMPLEMENTATION_STATUS.md) for exact coverage.
