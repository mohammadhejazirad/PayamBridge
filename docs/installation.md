# Installation

PayamBridge has two Composer packages: the framework-independent **payambridge/sdk** and the optional **payambridge/laravel** bridge. Both require PHP 8.3, 8.4, or 8.5. The bridge requires Laravel Illuminate 12 or 13. The SDK requires ext-json and ext-mbstring; the SQLite inbox additionally requires ext-pdo_sqlite. No Redis, Node, or database is required for sending.

## First public release

Once version 0.1.0 of **both packages** is published and indexed by Packagist, use:

~~~sh
composer require payambridge/sdk:^0.1
~~~

For Laravel:

~~~sh
composer require payambridge/laravel:^0.1
php artisan vendor:publish --tag=payambridge-config
~~~

The Laravel package requires the SDK. These registry commands are conditional on publication; check [release status](IMPLEMENTATION_STATUS.md) before relying on them. Composer resolves against your application's own lock and PHP version. Commit the application's composer.lock.

## Install from this checkout before publication

Add path repositories to the consuming application's composer.json, replacing the absolute path. Assign the first release version explicitly to each local package so the bridge's ^0.1 SDK dependency resolves.

~~~json
{
  "repositories": [
    {
      "type": "path",
      "url": "/absolute/path/PayamBridge",
      "options": {
        "versions": {"payambridge/sdk": "0.1.0"},
        "symlink": false
      }
    },
    {
      "type": "path",
      "url": "/absolute/path/PayamBridge/packages/laravel",
      "options": {
        "versions": {"payambridge/laravel": "0.1.0"},
        "symlink": false
      }
    }
  ],
  "require": {
    "payambridge/laravel": "^0.1"
  }
}
~~~

Merge these entries into the existing application manifest. Run `composer require payambridge/laravel:^0.1 --with-all-dependencies`. For standalone PHP, keep only the SDK repository and run `composer require payambridge/sdk:^0.1`. A copied path installation (symlink false) exposes missing files and development-autoload mistakes. The root composer.lock is for development verification; a library consumer resolves its own lock. The bridge's package directory is not part of the SDK distribution archive, so install the bridge separately.

## Credentials

Set BALE_BOT_TOKEN for the standalone Bale example or the configured PAYAMBRIDGE_BALE_TOKEN, PAYAMBRIDGE_RUBIKA_TOKEN, and PAYAMBRIDGE_EITAA_APP_TOKEN references for Laravel. Never put real tokens into composer.json, committed config, code, job payloads, or screenshots. The Laravel config stores environment variable names. With config:cache, set secrets in the real PHP-FPM/worker environment or replace CredentialResolver. Obtain tokens from the provider and send only to a destination you are authorized to use.

The default HTTP transport verifies TLS, disables redirects, and applies connect/read/total timeouts. It does not follow a user-supplied URL. A custom PSR-18 client must enforce equivalent policy.

## Verify a checkout

~~~sh
composer install
composer check
~~~

composer check runs strict Composer validation, Pint, PHPStan, PHPUnit, and operation inventory. Testbench and Laravel in root require-dev are development tools; they are not runtime dependencies of an SDK-only consumer. See [implementation status](IMPLEMENTATION_STATUS.md) for actual tested environments and [release process](releasing.md) for packaging.

Development checks additionally require `ext-pdo_sqlite` and `ext-zip`. `composer check` also inspects Composer distribution archives. Use `composer test:repeat` for three consecutive suite runs and `composer test:consumer` for clean standalone/Laravel application installations.
