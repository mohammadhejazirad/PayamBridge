# Contributing

PayamBridge contains the standalone PHP SDK in `src/` and the Laravel integration in `packages/laravel/`. Keep provider behavior in its own driver and avoid making the SDK depend on Laravel. Tests use synthetic fixtures and fakes; never commit real credentials or customer data.

## Local checks

Use PHP 8.3–8.5 and Composer 2 with `mbstring`, `pdo_sqlite`, and `zip`. From the repository root:

```sh
composer install
composer check
composer validate --strict packages/laravel/composer.json
composer audit
php tools/consumer-smoke.php php
php tools/consumer-smoke.php 12
php tools/consumer-smoke.php 13
```

The consumer checks create temporary projects and need access to public Composer packages. CI tests PHP 8.3, 8.4, and 8.5 with Laravel 12 and 13, using both lowest and latest dependency resolution. Add focused tests for behavior changes. Include provider documentation sources and sanitized fixtures when adding API operations; update the operation inventory and limitations rather than assuming parity across providers.

Open a pull request against `main` with a short behavior summary, test evidence, and any compatibility or credential-handling effects. Releases follow [the release guide](docs/releasing.md).

The committed development lock targets PHP 8.3 so `composer install` works on every supported PHP minor. To resolve dependencies for the actual current runtime (as CI does), run `composer config --unset platform.php` in an isolated checkout before `composer update`. Select `orchestra/testbench:^10.0` for Laravel 12 or `^11.0` for Laravel 13. Do not change runtime package PHP constraints to bypass a failing compatibility check.
