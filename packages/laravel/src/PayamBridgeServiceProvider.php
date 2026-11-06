<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PayamBridge\ConnectionFactory;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\Timeouts;
use PayamBridge\Laravel\Commands\CapabilitiesCommand;
use PayamBridge\Runtime\InboxStore;
use PayamBridge\Runtime\Poller;

final class PayamBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payambridge.php', 'payambridge');
        if (! $this->app->bound(CredentialResolver::class)) {
            $this->app->bind(CredentialResolver::class, EnvironmentCredentialResolver::class);
        }
        if (! $this->app->bound(HttpTransport::class)) {
            $this->app->scoped(HttpTransport::class, static function (Application $app): HttpTransport {
                $config = $app->make('config')->get('payambridge.timeouts');

                return HttpTransport::createDefault(new Timeouts((float) $config['connect'], (float) $config['read'], (float) $config['total']));
            });
        }
        $this->app->scoped(ConnectionFactory::class);
        $this->app->scoped(ConnectionManager::class);
        if (! $this->app->bound(InboxStore::class)) {
            $this->app->scoped(InboxStore::class, static fn (Application $app): InboxStore => InboxStoreFactory::make($app));
        }
        if (! $this->app->bound(Poller::class)) {
            $this->app->scoped(Poller::class, static function (Application $app): Poller {
                $lease = $app->make('config')->get('payambridge.inbox.lease_seconds');
                if ((! is_float($lease) && ! is_int($lease)) || ! is_finite((float) $lease) || $lease <= 0) {
                    throw new InvalidArgumentException('payambridge.inbox.lease_seconds must be finite and positive.');
                }

                return new Poller($app->make(InboxStore::class), (float) $lease);
            });
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/payambridge.php' => config_path('payambridge.php')], 'payambridge-config');
            $this->commands([CapabilitiesCommand::class]);
        }
    }
}
