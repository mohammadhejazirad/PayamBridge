<?php

declare(strict_types=1);

namespace PayamBridge\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PayamBridge\ConnectionFactory;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Http\Timeouts;
use PayamBridge\Laravel\Commands\CapabilitiesCommand;

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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/payambridge.php' => config_path('payambridge.php')], 'payambridge-config');
            $this->commands([CapabilitiesCommand::class]);
        }
    }
}
