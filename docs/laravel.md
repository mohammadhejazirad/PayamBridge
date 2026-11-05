# Laravel bridge

Install both local packages using `installation.md`. Only the bridge has Laravel dependencies. Its ServiceProvider is auto-discovered; config publishing is optional. It installs no routes, migrations or daemons.

## Credentials and workers

Configuration stores `token_env` references such as `PAYAMBRIDGE_BALE_TOKEN`, never the credential itself. Bind `CredentialResolver` to a vault implementation if needed. With config cache enabled, provide referenced secrets in the real worker/FPM environment; `.env` is not reliably reloaded. Connections are created afresh and resolve the latest secret. Resolve `ConnectionManager` in the job handler; do not serialize clients into a queue payload. Credential serialization is explicitly rejected.

The manager and transport bindings are scoped. There is no mutable current-tenant setter or shared credential cache. Each named connection has an explicit tenant and identity surface; the application must authorize which named connections each tenant can select. DI bindings can be overridden before use. The Facade is optional.

`php artisan payam:capabilities bale` lists local implemented capabilities without network requests. It requires the configured secret to construct the connection but never displays it. Doctor, polling, webhook and user-session commands remain unimplemented and are not advertised as working commands.

## Notifications without model changes

```php
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
```

```php
use Illuminate\Support\Facades\Notification;
use PayamBridge\Laravel\Notifications\NotificationRoute;

Notification::route('payambridge', new NotificationRoute('bale', '123'))
    ->notify(new OrderRegistered());
```

A normal notifiable may instead implement `routeNotificationForPayambridge` and return `NotificationRoute`, or null to skip delivery. This does not require a package-specific User base class. Add `ShouldQueue` and Laravel's `Queueable` trait to the notification when appropriate; credentials are resolved at delivery time.

For order-dependent notifications, use Laravel after-commit behavior or dispatch after the transaction commits. A rolled-back order must not produce a confirmed notification. **Queued writes can be delivered twice if the worker retries after a successful send whose response was lost.** Treat `AmbiguousDeliveryException` as a reconciliation/manual-review outcome; configure consumer job failure policy rather than using blind `--tries` retries. A persistent outbox is still planned.

Testbench exercises notification dispatch, real database queue serialization/worker processing, local capability command, config publishing/cache and route cache. Clean-consumer installation/discovery checks are reported separately. No provider webhook route is registered; webhook authenticity, durable ingress and storage failure responses are future work.
